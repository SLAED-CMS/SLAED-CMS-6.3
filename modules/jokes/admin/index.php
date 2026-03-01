<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('jokes')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=conf', 'name=jokes&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    return getAdminTabs(_JOKES, 'jokes.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function jokes(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['jokes']['anum'] ?? 25;
    $anump = $conf['jokes']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=jokes&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 2, 0, 0);
    } else {
        $status = '1';
        $field = 'name=jokes&amp;';
        $refer = '';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->sql_query('SELECT j.jokeid, j.name, j.date, j.title, j.cat, j.ip_sender, c.title, u.user_name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cat = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.user_id) WHERE j.status = :status ORDER BY j.date DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$jokeid, $uname, $date, $title, $cat, $ip, $ctitle, $nick] = $db->sql_fetchrow($result)) {
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
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=jokes&amp;op=add&amp;id='.$jokeid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=jokes&amp;op=del&amp;id='.$jokeid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'jokeid', '_jokes', '', 'status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop;
    $id = getVar('req', 'id', 'num', 0);
    $jokeid = $id;
    if ($jokeid) {
        $result = $db->sql_query('SELECT j.jokeid, j.name, j.date, j.title, j.cat, j.joke, u.user_name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.user_id) WHERE j.jokeid = :jokeid', ['jokeid' => $jokeid]);
        [$jokeid, $uname, $date, $title, $cat, $joke, $nick] = $db->sql_fetchrow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $jokeid = getVar('post', 'jokeid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $date = save_datetime(1, 'date');
        $title = getVar('post', 'title', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $joke = getVar('post', 'joke', 'text', '');
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if (!empty($joke)) $cont .= preview($title, $joke, '', '', 'all');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('jokes', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._JOKE.':</td><td>'.textarea('1', 'joke', $joke, 'jokes', '10', _JOKE, '1').'</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="jokes">'.ad_save('jokeid', $jokeid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $jokeid = getVar('post', 'jokeid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $date = save_datetime(1, 'date');
    $title = getVar('post', 'title', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $joke = getVar('post', 'joke', 'text', '');
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$joke) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    if (!$jokeid && $db->sql_numrows($db->sql_query('SELECT title FROM '.PREFIX_DB.'_jokes WHERE title = :title', ['title' => $title])) > 0) $stop[] = _JOKEEXIST;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? text_filter(substr($postname, 0, 25)) : '';
        if ($jokeid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_jokes SET uid = :uid, name = :name, date = :date, title = :title, cat = :cat, joke = :joke, status = \'1\' WHERE jokeid = :jokeid', ['uid' => $postid, 'name' => $postname, 'date' => $date, 'title' => $title, 'cat' => $cat, 'joke' => $joke, 'jokeid' => $jokeid]);
        } else {
            $ip = getip();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_jokes (uid, name, date, title, cat, joke, ip_sender, status) VALUES (:uid, :name, :date, :title, :cat, :joke, :ip, \'1\')', ['uid' => $postid, 'name' => $postname, 'date' => $date, 'title' => $title, 'cat' => $cat, 'joke' => $joke, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=jokes');
    } elseif ($posttype === 'delete') {
        del($jokeid);
    } else {
        add();
    }
}

function del(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'jokes\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_jokes WHERE jokeid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=jokes');
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 3, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/jokes.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['jokes']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($conf['jokes']['num'] ?? 0).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['jokes']['anum'] ?? 0).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.($conf['jokes']['nump'] ?? 0).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['jokes']['anump'] ?? 0).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._HOMCAT.'</td><td>'.radio_form($conf['jokes']['homcat'] ?? 0, 'homcat').'</td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($conf['jokes']['catdesc'] ?? 0, 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($conf['jokes']['subcat'] ?? 0, 'subcat').'</td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['jokes']['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._J_1.'</td><td>'.radio_form($conf['jokes']['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._J_2.'</td><td>'.radio_form($conf['jokes']['addquest'] ?? 0, 'addquest').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($conf['jokes']['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($conf['jokes']['rate'] ?? 0, 'rate').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="jokes"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
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
    setRedirect($afile.'.php?name=jokes&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 4, 0, 0).'<div id="repadm_info">'.adm_info(1, 'jokes', 0).'</div>';
    setFoot();
}

switch ($op) {
    default: jokes(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}





