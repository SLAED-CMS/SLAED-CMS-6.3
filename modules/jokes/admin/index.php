<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('jokes')) die('Illegal file access');

function jokes(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['jokes']['anum'] ?? 25;
    $anump = $conf['jokes']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=jokes&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=jokes&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT j.id, j.name, j.time, j.title, j.cid, j.ip, c.title, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id) WHERE j.status = :status ORDER BY j.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$jokeid, $uname, $date, $title, $cat, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cat) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status && time() >= strtotime($date)) {
                $view = '<a href="index.php?name=jokes&amp;cat='.$cat.'#'.$jokeid.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$jokeid.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=jokes&amp;op=add&amp;id='.$jokeid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=jokes&amp;op=delete&amp;id='.$jokeid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_jokes', '', 'status = \''.$status.'\'', $anump);
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $jokeid = $id;
    if ($jokeid) {
        $result = $db->getSqlQuery('SELECT j.id, j.name, j.time, j.title, j.cid, j.body, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id) WHERE j.id = :jokeid', ['jokeid' => $jokeid]);
        [$jokeid, $uname, $date, $title, $cat, $joke, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $jokeid = getVar('post', 'jokeid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $date = getVar('req', 'date', 'time');
        $title = getVar('post', 'title', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $joke = getVar('post', 'joke', 'text', '');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    if (!empty($joke)) $cont .= preview($title, $joke, '', '', 'all');
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('jokes', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._JOKE.':</td><td>'.textarea('1', 'joke', $joke, 'jokes', '10', _JOKE, '1').'</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="jokes">'.ad_save('jokeid', $jokeid, 'save').'</td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $jokeid = getVar('post', 'jokeid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $date = getVar('req', 'date', 'time');
    $title = getVar('post', 'title', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $joke = getVar('post', 'joke', 'text', '');
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$joke) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    if (!$jokeid && $db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_jokes WHERE title = :title', ['title' => $title])) > 0) $stop[] = _JOKEEXIST;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
        if ($jokeid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_jokes SET uid = :uid, name = :name, time = :time, title = :title, cid = :cat, body = :joke, status = \'1\' WHERE id = :jokeid', ['uid' => $postid, 'name' => $postname, 'time' => $date, 'title' => $title, 'cat' => $cat, 'joke' => $joke, 'jokeid' => $jokeid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_jokes (uid, name, time, title, cid, body, ip, status) VALUES (:uid, :name, :time, :title, :cat, :joke, :ip, \'1\')', ['uid' => $postid, 'name' => $postname, 'time' => $date, 'title' => $title, 'cat' => $cat, 'joke' => $joke, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=jokes');
    } elseif ($posttype === 'delete') {
        delete($jokeid);
    } else {
        add();
    }
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'jokes\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_jokes WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=jokes');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/jokes.php');
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'jokes',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['jokes']['defis'] ?? ''),
        '_c33' => _C_33,
        'num' => $conf['jokes']['num'] ?? 0,
        '_c34' => _C_34,
        'anum' => $conf['jokes']['anum'] ?? 0,
        '_c35' => _C_35,
        'nump' => $conf['jokes']['nump'] ?? 0,
        '_c36' => _C_36,
        'anump' => $conf['jokes']['anump'] ?? 0,
        '_homcat' => _HOMCAT,
        'r_homcat' => radio_form($conf['jokes']['homcat'] ?? 0, 'homcat'),
        '_c32' => _C_32,
        'r_catdesc' => radio_form($conf['jokes']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'r_subcat' => radio_form($conf['jokes']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['jokes']['addmail'] ?? 0, 'addmail'),
        '_j1' => _J_1,
        'r_add' => radio_form($conf['jokes']['add'] ?? 0, 'add'),
        '_j2' => _J_2,
        'r_addquest' => radio_form($conf['jokes']['addquest'] ?? 0, 'addquest'),
        '_c17' => _C_17,
        'r_date' => radio_form($conf['jokes']['date'] ?? 0, 'date'),
        '_c19' => _C_19,
        'r_rate' => radio_form($conf['jokes']['rate'] ?? 0, 'rate'),
        'jokes' => true,
    ]);
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'homcat' => getVar('post', 'homcat', 'num', 0),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
    ];
    setConfigFile('jokes.php', $cont);
    setRedirect($afile.'.php?name=jokes&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 4]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: jokes(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}





