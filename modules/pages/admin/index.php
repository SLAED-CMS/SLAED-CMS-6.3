<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('pages')) die('Illegal file access');

function pages(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['pages']['anum'] ?? 25;
    $anump = $conf['pages']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=pages&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=pages&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT p.id, p.cid, p.name, p.title, p.time, p.ip, t.title, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_categories AS t ON (p.cid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE p.status = :status ORDER BY p.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status && time() >= strtotime($time)) {
                $view = '<a href="index.php?name=pages&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=pages&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=pages&amp;op=delete&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_pages', '', 'status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $pid = $id;
    if ($pid) {
        $result = $db->getSqlQuery('SELECT p.cid, p.name, p.title, p.time, p.intro, p.body, p.ihome, p.acomm, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE id = :pid', ['pid' => $pid]);
        [$cat, $uname, $subject, $time, $hometext, $bodytext, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $time = getVar('req', 'time', 'time');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $ihome = getVar('post', 'ihome', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    if ($hometext) $cont .= preview($subject, $hometext, $bodytext, '', 'pages');
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="subject" value="'.$subject.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('pages', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, 'pages', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'pages', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="pages">'.ad_save('pid', $pid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $pid = getVar('post', 'pid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = getVar('req', 'time', 'time');
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
        if ($pid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET cid = :cat, uid = :uid, name = :name, title = :title, time = :time, intro = :intro, body = :body, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :pid', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'pid' => $pid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_pages (id, cid, uid, name, title, time, intro, body, comments, counter, ihome, acomm, score, ratings, ip, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :intro, :body, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :ip, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=pages');
    } elseif ($posttype === 'delete') {
        delete($pid);
    } else {
        add();
    }
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'pages\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'pages\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_pages WHERE id = :id', ['id' => $id]);
    }
    $refer = getVar('req', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=pages'.$refer);
}

function config(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/pages.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'        => $afile,
        '{%module%}'       => 'pages',
        '{%op%}'           => 'configsave',
        '{%save%}'         => _SAVECHANGES,
        '{%fields%}'       => '',
        '{%_cdefis%}'      => _CDEFIS,
        '{%defis%}'        => urldecode($conf['pages']['defis'] ?? ''),
        '{%_pagelinknum%}' => _PAGELINKNUM,
        '{%linknum%}'      => $conf['pages']['linknum'] ?? 10,
        '{%_c13%}'         => _C_13,
        '{%listnum%}'      => $conf['pages']['listnum'] ?? 10,
        '{%_c33%}'         => _C_33,
        '{%num%}'          => $conf['pages']['num'] ?? 25,
        '{%_c34%}'         => _C_34,
        '{%anum%}'         => $conf['pages']['anum'] ?? 25,
        '{%_c35%}'         => _C_35,
        '{%nump%}'         => $conf['pages']['nump'] ?? 10,
        '{%_c36%}'         => _C_36,
        '{%anump%}'        => $conf['pages']['anump'] ?? 10,
        '{%_homcat%}'      => _HOMCAT,
        '{%r_homcat%}'     => radio_form($conf['pages']['homcat'] ?? 0, 'homcat'),
        '{%_viewcat%}'     => _VIEWCAT,
        '{%r_viewcat%}'    => radio_form($conf['pages']['viewcat'] ?? 0, 'viewcat'),
        '{%_c32%}'         => _C_32,
        '{%r_catdesc%}'    => radio_form($conf['pages']['catdesc'] ?? 0, 'catdesc'),
        '{%_c15%}'         => _C_15,
        '{%r_subcat%}'     => radio_form($conf['pages']['subcat'] ?? 0, 'subcat'),
        '{%_addamail%}'    => _ADDAMAIL,
        '{%r_addmail%}'    => radio_form($conf['pages']['addmail'] ?? 0, 'addmail'),
        '{%_c39%}'         => _C_39,
        '{%r_add%}'        => radio_form($conf['pages']['add'] ?? 0, 'add'),
        '{%_c40%}'         => _C_40,
        '{%r_addquest%}'   => radio_form($conf['pages']['addquest'] ?? 0, 'addquest'),
        '{%_c37%}'         => _C_37,
        '{%r_autor%}'      => radio_form($conf['pages']['autor'] ?? 0, 'autor'),
        '{%_c17%}'         => _C_17,
        '{%r_date%}'       => radio_form($conf['pages']['date'] ?? 0, 'date'),
        '{%_c18%}'         => _C_18,
        '{%r_read%}'       => radio_form($conf['pages']['read'] ?? 0, 'read'),
        '{%_c19%}'         => _C_19,
        '{%r_rate%}'       => radio_form($conf['pages']['rate'] ?? 0, 'rate'),
        '{%_c20%}'         => _C_20,
        '{%r_letter%}'     => radio_form($conf['pages']['letter'] ?? 0, 'letter'),
        '{%_pagelink%}'    => _PAGELINK,
        '{%r_link%}'       => radio_form($conf['pages']['link'] ?? 0, 'link'),
        'if_flag'          => ['pages' => true],
    ]);
    $cont .= setTemplateBasic('close');
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
    setConfigFile('pages.php', $cont);
    setRedirect($afile.'.php?name=pages&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 4]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: pages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}





