<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('faq')) die('Illegal file access');

function faq(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['faq']['anum'] ?? 25;
    $anump = $conf['faq']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=faq&amp;status=1&amp;';
        $refer = '&op=faq&status=1';
        $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=config', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=faq&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=config', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, f.ip, t.title, u.name FROM '.PREFIX_DB.'_faq AS f LEFT JOIN '.PREFIX_DB.'_categories AS t ON (f.cid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-article-list-head', [
            'checkall_html' => '',
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'postedby_label' => _POSTEDBY,
            'status_label' => _STATUS,
            'title_label' => _QUESTION,
        ]);
        $rows = '';
        while ([$id, $cid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status == '1' && time() >= strtotime($time)) {
                $view = adminLinkAction('index.php?name=faq&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW);
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $acts = adminMenuItems([
                $view,
                adminLinkAction($afile.'.php?name=faq&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=faq&amp;op=delete&amp;id='.$id.$refer, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-article-list-row', [
                'actions_html' => $acts,
                'checkbox_html' => '',
                'id_text' => (string)$id,
                'post_html' => $post,
                'status_html' => ad_status('', $active),
                'title_html' => adminTitleTipLabel(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip, $title, cutstr($title, 60)),
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_faq', '', 'status = \''.$status.'\'', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $fid = $id;
    if ($fid) {
        $result = $db->getSqlQuery('SELECT s.cid, s.name, s.title, s.time, s.body, s.ihome, s.acomm, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE id = :fid', ['fid' => $fid]);
        [$cat, $uname, $subject, $time, $hometext, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = getVar('req', 'time', 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=config', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    if ($hometext) $cont .= preview($subject, $hometext, '', '', 'faq');
    $hide = getAdminHidden('name', 'faq');
    $rows = $tpl->getHtmlFrag('admin-faq-add-rows', [
        'acomm_html' => com_access('acomm', $acomm, 'sl_form'),
        'acomm_label' => _COMMENTS.':',
        'answer_html' => textarea('1', 'hometext', $hometext, 'faq', '10', _ANSWER, '1'),
        'answer_label' => _ANSWER.':',
        'cat_html' => getcat('faq', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>'),
        'cat_label' => _CATEGORY.':',
        'ihome_html' => radio_form($ihome, 'ihome'),
        'ihome_label' => _PUBHOME,
        'postname_html' => getUserSearch('postname', $postname, '25', 'sl_form', '1'),
        'postname_label' => _POSTEDBY.':',
        'save_html' => ad_save('fid', $fid, 'save'),
        'subject_label' => _TITLE.' / '._QUESTION.':',
        'subject_value' => $subject,
        'time_html' => datetime(1, 'time', $time, 16, 'sl_form'),
        'time_label' => _CHNGSTORY.':',
    ]);
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $fid = getVar('post', 'fid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = getVar('req', 'time', 'time');
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype == 'save') {
        $postid = (is_user_id($postname)) ? is_user_id($postname) : 0;
        $postname = (!is_user_id($postname)) ? filterText(substr($postname, 0, 25)) : '';
        if ($fid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET cid = :cat, uid = :postid, name = :postname, title = :subject, time = :time, body = :body, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :fid', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_faq (cid, uid, name, title, time, body, ihome, acomm, ip, status) VALUES (:cat, :postid, :postname, :subject, :time, :body, :ihome, :acomm, :ip, \'1\')', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=faq');
    } elseif ($posttype == 'delete') {
        delete($fid);
    } else {
        add();
    }
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_faq WHERE id = :id', ['id' => $id]);
    }
    $refer = getVar('get', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=faq'.$refer);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=config', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/faq.php');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'faq',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['faq']['defis'] ?? ''),
        '_pagelinknum' => _PAGELINKNUM,
        'linknum' => $conf['faq']['linknum'] ?? 0,
        '_c13' => _C_13,
        'listnum' => $conf['faq']['listnum'] ?? 0,
        '_c33' => _C_33,
        'num' => $conf['faq']['num'] ?? 0,
        '_c34' => _C_34,
        'anum' => $conf['faq']['anum'] ?? 0,
        '_c35' => _C_35,
        'nump' => $conf['faq']['nump'] ?? 0,
        '_c36' => _C_36,
        'anump' => $conf['faq']['anump'] ?? 0,
        '_homcat' => _HOMCAT,
        'r_homcat' => radio_form($conf['faq']['homcat'] ?? 0, 'homcat'),
        '_viewcat' => _VIEWCAT,
        'r_viewcat' => radio_form($conf['faq']['viewcat'] ?? 0, 'viewcat'),
        '_c32' => _C_32,
        'r_catdesc' => radio_form($conf['faq']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'r_subcat' => radio_form($conf['faq']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['faq']['addmail'] ?? 0, 'addmail'),
        '_c39' => _C_39,
        'r_add' => radio_form($conf['faq']['add'] ?? 0, 'add'),
        '_c40' => _C_40,
        'r_addquest' => radio_form($conf['faq']['addquest'] ?? 0, 'addquest'),
        '_c37' => _C_37,
        'r_autor' => radio_form($conf['faq']['autor'] ?? 0, 'autor'),
        '_c17' => _C_17,
        'r_date' => radio_form($conf['faq']['date'] ?? 0, 'date'),
        '_c18' => _C_18,
        'r_read' => radio_form($conf['faq']['read'] ?? 0, 'read'),
        '_c19' => _C_19,
        'r_rate' => radio_form($conf['faq']['rate'] ?? 0, 'rate'),
        '_c20' => _C_20,
        'r_letter' => radio_form($conf['faq']['letter'] ?? 0, 'letter'),
        '_pagelink' => _PAGELINK,
        'r_link' => radio_form($conf['faq']['link'] ?? 0, 'link'),
        'faq' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'linknum' => getVar('post', 'linknum', 'num', 10),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'homcat' => getVar('post', 'homcat', 'num', 0),
        'viewcat' => getVar('post', 'viewcat', 'num', 0),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
        'autor' => getVar('post', 'autor', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
        'link' => getVar('post', 'link', 'num', 0),
    ];
    setConfigFile('faq.php', $cont);
    setRedirect($afile.'.php?name=faq&op=config');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=config', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 4]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: faq(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
