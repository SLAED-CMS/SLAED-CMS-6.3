<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('help')) die('Illegal file access');

function help(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['help']['anum'] ?? 25;
    $anump = $conf['help']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '1';
        $field = 'name=help&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO], 'tab' => 1]);
    } else {
        $status = '0';
        $field = 'name=help&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.comments, s.ip, s.status, c.title, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.pid = \'0\' AND s.status = :status ORDER BY s.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-help-list-head', [
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'messages_label' => cutstr(_MESSAGES, 4, 1),
            'postedby_label' => _POSTEDBY,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $cid, $title, $time, $comments, $ip, $stat, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : _ANONYM;
            $stat = ($stat) ? 0 : 1;
            $acts = adminMenuItems([
                adminLinkAction($afile.'.php?name=help&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW),
                adminDeleteAction($afile.'.php?name=help&amp;op=delete&amp;id='.$id.$refer, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-help-list-row', [
                'actions_html' => $acts,
                'comments_text' => (string)$comments,
                'id_text' => (string)$id,
                'post_html' => $post,
                'status_html' => ad_status('', $stat),
                'title_html' => adminTitleTipLabel(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip, $title, cutstr($title, 60)),
            ]));
        }
        $cont .= getTplAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_help', '', 'pid = \'0\' AND status = \''.$status.'\'', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $tpl;
    $vid = intval(getVar('get', 'id', 'num', 0) ?? 0);
    $result = $db->getSqlQuery('SELECT s.id, s.pid, s.uid, s.aid, s.title, s.time, s.body, s.field, s.counter, s.score, s.ratings, c.title, c.intro, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE s.id = :id1 OR s.pid = :id2 AND s.time <= now() ORDER BY s.time ASC', ['id1' => $vid, 'id2' => $vid]);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO]]);
    $html = '';
    $a = 0;
    while ([$id, $pid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
        $title = ($title) ? $title : _MESSAGE.': '.$a;
        $fields = fields_out($field, 'help');
        $fields = ($fields) ? '<br><br>'.$fields : '';
        $text = $hometext.$fields;
        $post = $nick ? user_info($nick) : _ANONYM;
        $post = getTplSpan('sl_post', $post, _POSTEDBY);
        $date = getTplSpan('sl_date', format_time($time, _TIMESTRING), _CHNGSTORY);
        $comm = ($a) ? getTplAdminTextLink('#'.$id, (string)$a, '', _MESSAGE.': '.$a, 'sl_pnum') : '';
        $rating = ($haid && $huid != $haid) ? getRatingAsync(0, $id, 'help', $ratings, $score, '') : '';
        if (!$pid) {
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? getTplSpan('sl_cat', cutstr($ctitle, 15), $cdesc) : '';
            $reads = getTplSpan('sl_views', (string)$counter, _READS);
        } else {
            $ctitle = '';
            $reads =  '';
        }
        $admin = adminMenuItems([
            adminLinkAction($afile.'.php?name=help&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
            adminDeleteAction($afile.'.php?name=help&amp;op=delete&amp;id='.$id, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
        ]);
        $html .= $tpl->getHtmlFrag('basic', ['ctitle' => $ctitle, 'id' => $id, 'title' => $title, 'text' => filterReplaceText(filterMarkdown($text, 'help', false), 'help'), 'post' => $post, 'date' => $date, 'reads' => $reads, 'comm' => $comm, 'rating' => $rating, 'admin' => $admin]);
        $a++;
    }
    $cont .= getTplBox($html);
    $cont .= addview($vid);
    echo $cont;
    setFoot();
}

function addview(int $id): string {
    global $db, $afile, $admin, $tpl;
    $result = $db->getSqlQuery('SELECT cid, uid, status FROM '.PREFIX_DB.'_help WHERE id = :id', ['id' => $id]);
    [$cid, $uid, $status] = $db->getSqlRow($result);
    $rows = $tpl->getHtmlFrag('admin-help-addview-rows', [
        'hometext_html' => textarea('1', 'hometext', '', 'help', '10', _TEXT, '1'),
        'hometext_label' => _TEXT.':',
        'postname_html' => getUserSearch('postname', $admin[1] ?? '', '25', 'sl_form', '1'),
        'postname_label' => _POSTEDBY.':',
        'save_label' => _SEND,
        'status_html' => radio_form($status, 'status'),
        'status_label' => _HELPGLOS,
        'umail_html' => radio_form('1', 'umail'),
        'umail_label' => _MAIL_SENDE,
    ]);
    $hide = getTplHiddenInput('name', 'help').getTplHiddenInput('refer', '1').getTplHiddenInput('pid', (string)$id).getTplHiddenInput('cat', (string)$cid).getTplHiddenInput('uid', (string)$uid).getTplHiddenInput('posttype', 'save').getTplHiddenInput('op', 'save');
    return getTplAdminForm($afile.'.php', $rows, $hide);
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->getSqlQuery('SELECT s.pid, s.cid, s.title, s.time, s.body, s.field, s.status, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE s.id = :id', ['id' => $id]);
        [$pid, $cat, $subject, $time, $hometext, $field, $status, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: _ANONYM;
    } else {
        $id = getVar('post', 'id', 'num', 0);
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = getVar('req', 'time', 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $field = getVar('post', 'field', 'field');
    }
    $status = getVar('post', 'status', 'num', 0) ? getVar('post', 'status', 'num', 0) : ($status ?? 0);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO]]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => getStopText((array)$stop)]);
    if (!empty($hometext)) $cont .= preview($subject, $hometext, '', $field, 'help');
    $rows = $tpl->getHtmlFrag('admin-help-add-rows', [
        'cat_html' => getcat('help', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>'),
        'cat_label' => _CATEGORY.':',
        'field_html' => fields_in($field, 'help'),
        'hometext_html' => textarea('1', 'hometext', $hometext, 'help', '10', _TEXT, '1'),
        'hometext_label' => _TEXT.':',
        'is_reply' => (bool)$pid,
        'postname_html' => getUserSearch('postname', $postname, '25', 'sl_form', '1'),
        'postname_label' => _POSTEDBY.':',
        'save_html' => ad_save('id', $id, 'save'),
        'subject_label' => _TITLE.':',
        'subject_value' => $subject,
        'time_html' => datetime(1, 'time', $time, 16, 'sl_form'),
        'time_label' => _CHNGSTORY.':',
    ]);
    $hide = getTplHiddenInput('name', 'help').getTplHiddenInput('pid', (string)$pid);
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $admin, $conf, $stop;
    $id = getVar('post', 'id', 'num', 0);
    $pid = getVar('post', 'pid', 'num', 0);
    $uid = getVar('post', 'uid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $field = getVar('post', 'field', 'field');
    $time = getVar('req', 'time', 'time');
    $status = getVar('post', 'status', 'num', 0);
    $umail = getVar('post', 'umail', 'text', '');
    $stop = [];
    if (!$subject && !$pid) $stop[] = _CERROR;
    if (!$hometext && !$pid) $stop[] = _CERROR1;
    if (!$postname && !$pid) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = (is_user_id($postname)) ? is_user_id($postname) : 0;
        if ($id) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET cid = :cat, aid = :postid, title = :subject, time = :time, body = :body, field = :field WHERE id = :id', ['cat' => $cat, 'postid' => $postid, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'field' => $field, 'id' => $id]);
            $hid = ($pid) ? $pid : $id;
            setRedirect($afile.'.php?name=help&op=view&id='.$hid);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_help (pid, cid, uid, aid, title, time, body, field, ip, status) VALUES (:pid, :cat, :uid, :postid, :subject, now(), :body, \'\', :ip, \'0\')', ['pid' => $pid, 'cat' => $cat, 'uid' => $uid, 'postid' => $postid, 'subject' => $subject, 'body' => $hometext, 'ip' => $ip]);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET comments = comments+1, status = :status WHERE id = :pid', ['status' => $status, 'pid' => $pid]);
            if ($umail) {
                $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]);
                if ($db->getSqlRowCount($result) == 1) {
                    [$mail] = $db->getSqlRow($result);
                    $finishlink = ($conf['homeurl'] ?? '').'/index.php?name=help&amp;op=view&amp;id='.$pid;
                    $link = '<a href="'.$finishlink.'">'.$finishlink.'</a>';
                    $subject = ($conf['sitename'] ?? '').' - '._HELP;
                    $message = str_replace('[text]', sprintf(_ADDMAILU, substr($admin[1] ?? '', 0, 25), _HELP, $link), $conf['mtemp'] ?? '');
                    addMail($mail, $conf['adminmail'] ?? '', $subject, $message, 0, 3);
                }
            }
            setRedirect($afile.'.php?name=help');
        }
    } elseif ($posttype === 'delete') {
        delete($id);
    } else {
        add();
    }
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'help\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_help WHERE id = :id1 OR pid = :id2', ['id1' => $id, 'id2' => $id]);
    }
    setRedirect($afile.'.php?name=help');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/help.php');
    $cont .= getTplBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'help',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['help']['defis'] ?? ''),
        '_c13' => _C_13,
        'listnum' => $conf['help']['listnum'] ?? 0,
        '_c33' => _C_33,
        'num' => $conf['help']['num'] ?? 0,
        '_c34' => _C_34,
        'anum' => $conf['help']['anum'] ?? 0,
        '_c35' => _C_35,
        'nump' => $conf['help']['nump'] ?? 0,
        '_c36' => _C_36,
        'anump' => $conf['help']['anump'] ?? 0,
        '_c32' => _C_32,
        'r_catdesc' => radio_form($conf['help']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'r_subcat' => radio_form($conf['help']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['help']['addmail'] ?? 0, 'addmail'),
        '_helpadd' => _HELPADD,
        'r_add' => radio_form($conf['help']['add'] ?? 0, 'add'),
        '_c17' => _C_17,
        'r_date' => radio_form($conf['help']['date'] ?? 0, 'date'),
        '_c18' => _C_18,
        'r_read' => radio_form($conf['help']['read'] ?? 0, 'read'),
        '_c20' => _C_20,
        'r_letter' => radio_form($conf['help']['letter'] ?? 0, 'letter'),
        'help' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
    ];
    setConfigFile('help.php', $cont);
    setRedirect($afile.'.php?name=help&op=config');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO], 'tab' => 3]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: help(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
