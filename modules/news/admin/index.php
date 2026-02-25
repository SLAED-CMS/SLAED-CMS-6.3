<?php
# Author: Eduard Laas
# Copyright 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('news')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=news', 'name=news&amp;op=add', 'name=news&amp;status=1', 'name=news&amp;op=conf', 'name=news&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    return getAdminTabs(_NEWS, 'news.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function news(): void {
    global $db, $afile, $conf, $confu;
    $cfg = $conf['news'] ?? [];
    head();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $cfg['anum'] ?? 25;
    $anump = $cfg['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=news&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 2, 0, 0);
    } else {
        $status = '1';
        $field = 'name=news&amp;';
        $refer = '';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->sql_query('SELECT s.sid, s.catid, s.name, s.title, s.time, s.vote, s.ip_sender, c.title, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) WHERE s.status = :status ORDER BY s.fix DESC, s.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<form name="post" action="'.$afile.'.php" method="post"><input type="hidden" name="name" value="news"><input type="hidden" name="op" value="admin"><input type="hidden" name="refer" value="1">'
        .'<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th><th class="{sorter: false}"><input type="checkbox" name="markcheck" id="markcheck" title="'._CHECKALL.'" OnClick="CheckBox(\'#markcheck\', \'.sl_check\')"></th></tr></thead><tbody>';
        while ([$sid, $catid, $uname, $title, $time, $vote, $ip_sender, $ctitle, $user_name] = $db->sql_fetchrow($result)) {
            $ctitle = ($catid) ? $ctitle : _NO;
            $ip_sender = ($ip_sender) ? user_geo_ip($ip_sender, 4) : _NO;
            $post = $user_name ? user_info($user_name) : ($uname ?: $confu['anonym']);
            if ($status && time() >= strtotime($time)) {
                $ad_view = '<a href="index.php?name=news&amp;op=view&amp;id='.$sid.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $ad_view = '';
                $active = '0';
            }
            $ad_vote = ($vote) ? '<a href="'.$afile.'.php?name=voting&amp;op=add&amp;id='.$vote.'" title="'._EDITVOTE.'">'._EDITVOTE.'</a>||' : '';
            $cont .= '<tr><td>'.$sid.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip_sender).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($ad_view.$ad_vote.'<a href="'.$afile.'.php?name=news&amp;op=add&amp;id='.$sid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=news&amp;op=admin&amp;typ=d&amp;id='.$sid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td>'
            .'<td><input type="checkbox" name="id[]" class="sl_check" value="'.$sid.'"></td></tr>';
        }
        $cont .= '</tbody></table>';
        $selms = _CHECKOP.': '.edit_list('news', 'typ', '').' <input type="submit" value="'._OK.'" class="sl_but_blue">';
        $numpt = setArticleNumbers('pagenum', '', $anum, $field, 'sid', '_news', '', 'status = \''.$status.'\'', $anump);
        $cont .= '<table class="searchboxtab"><tr><td>'.$numpt.'</td><td><div class="searchbox">'.$selms.'</div></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $db, $afile, $confu, $stop;
    $sid = getVar('req', 'id', 'num', 0);
    if ($sid) {
        $result = $db->sql_query('SELECT s.catid, s.name, s.title, s.time, s.hometext, s.bodytext, s.field, s.vote, s.ihome, s.acomm, s.associated, s.fix, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) WHERE sid = :sid', ['sid' => $sid]);
        [$cat, $uname, $subject, $time, $hometext, $bodytext, $field, $vote, $ihome, $acomm, $associated, $fix, $user_name] = $db->sql_fetchrow($result);
        $associated = explode(',', $associated);
        $postname = $user_name ?: ($uname ?: $confu['anonym']);
    } else {
        $sid = getVar('post', 'sid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $associated = getVar('post', 'associated', 'array', []);
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $field = getVar('post', 'field', 'field');
        $vote = getVar('post', 'vote', 'num', 0);
        $time = save_datetime(1, 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $fix = getVar('post', 'fix', 'num', 0);
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    $homepre = ($vote) ? '<div id="repnews">'.getVoting($vote, 'news').'</div><hr>'.$hometext : $hometext;
    if ($homepre) $cont .= preview($subject, $homepre, $bodytext, $field, 'news');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _PAGENOTE]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="subject" value="'.$subject.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('news', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>';
    $result2 = $db->sql_query('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'news\' ORDER BY parentid, title');
    if ($db->sql_numrows($result2) > 0) {
        $cont .= '<tr><td>'._ASSOTOPIC.':<div class="sl_small">'._ASSOTOPICI.'</div></td><td><table class="sl_form"><tr>';
        $a = 0;
        while ([$cid, $ctitle] = $db->sql_fetchrow($result2)) {
            if ($a == 2) {
                $cont .= '</tr><tr>';
                $a = 0;
            }
            $check = '';
            if ($associated && is_array($associated)) {
                foreach ($associated as $val) {
                    if ((int)$val === (int)$cid) $check = ' checked';
                }
            }
            $cont .= '<td><input type="checkbox" name="associated[]" value="'.$cid.'"'.$check.'> '.$ctitle.'</td>';
            $a++;
        }
        $cont .= '</tr></table></td></tr>';
    }
    $cont .= '<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, 'news', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'news', '15', _ENDTEXT, '0').'</td></tr>'
    .fields_in($field, 'news')
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._VOTING.':</td><td>'.add_voting('news', 'vote', $vote, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td>'._FIXED.'?</td><td>'.radio_form($fix, 'fix').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="news">'.ad_save('sid', $sid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $sid = getVar('post', 'sid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $assoc_arr = getVar('post', 'associated', 'array', []);
    $associated = implode(',', is_array($assoc_arr) ? $assoc_arr : []);
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $field = getVar('post', 'field', 'field');
    $vote = getVar('post', 'vote', 'num', 0);
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = save_datetime(1, 'time');
    $fix = getVar('post', 'fix', 'num', 0);
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? text_filter(substr($postname, 0, 25)) : '';
        if ($sid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET catid = :cat, uid = :uid, name = :name, title = :title, time = :time, hometext = :hometext, bodytext = :bodytext, field = :field, vote = :vote, ihome = :ihome, acomm = :acomm, associated = :associated, fix = :fix, status = \'1\' WHERE sid = :sid', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'hometext' => $hometext, 'bodytext' => $bodytext, 'field' => $field, 'vote' => $vote, 'ihome' => $ihome, 'acomm' => $acomm, 'associated' => $associated, 'fix' => $fix, 'sid' => $sid]);
        } else {
            $ip = getip();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_news (sid, catid, uid, name, title, time, hometext, bodytext, field, vote, comments, counter, ihome, acomm, score, ratings, associated, ip_sender, fix, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :hometext, :bodytext, :field, :vote, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :associated, :ip, :fix, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'hometext' => $hometext, 'bodytext' => $bodytext, 'field' => $field, 'vote' => $vote, 'ihome' => $ihome, 'acomm' => $acomm, 'associated' => $associated, 'ip' => $ip, 'fix' => $fix]);
        }
        header('Location: '.$afile.'.php?name=news');
        exit;
    } elseif ($posttype === 'delete') {
        admin($sid, 'd');
    } else {
        add();
    }
}

function admin(int|array $ids = 0, string $vtyp = ''): void {
    global $db, $afile;
    $id_req = getVar('req', 'id', 'array', []);
    if (!is_array($id_req) || $id_req === []) {
        $single = getVar('req', 'id', 'num', 0);
        $id_req = ($single > 0) ? [$single] : [];
    }
    if (!is_array($ids)) $ids = ($ids > 0) ? [$ids] : [];
    $all_ids = array_unique(array_filter(array_map('intval', array_merge($id_req, $ids)), static fn($v): bool => $v > 0));
    $typ = $vtyp ?: getVar('post', 'typ', 'text', getVar('get', 'typ', 'text', ''));
    $refer = getVar('req', 'refer', 'num', 0) ? '&status=1' : '';
    if ($all_ids && $typ !== '') {
        $id_keys = [];
        $id_params = [];
        foreach (array_values($all_ids) as $i => $val) {
            $key = 'id'.$i;
            $id_keys[] = ':'.$key;
            $id_params[$key] = $val;
        }
        $in = implode(', ', $id_keys);
        if ($typ[0] === 'a') {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET status = :typ WHERE sid IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $id_params);
        } elseif ($typ[0] === 'f') {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET fix = :typ WHERE sid IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $id_params);
        } elseif ($typ[0] === 'h') {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET ihome = :typ WHERE sid IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $id_params);
        } elseif ($typ[0] === 't') {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET time = NOW() WHERE sid IN ('.$in.')', $id_params);
        } elseif ($typ[0] === 'c') {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET acomm = :typ WHERE sid IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $id_params);
        } elseif ($typ[0] === 'd') {
            $db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid IN ('.$in.') AND modul = \'news\'', $id_params);
            $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid IN ('.$in.') AND modul = \'news\'', $id_params);
            $db->sql_query('DELETE FROM '.PREFIX_DB.'_news WHERE sid IN ('.$in.')', $id_params);
        } elseif (is_numeric($typ)) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_news SET catid = :typ WHERE sid IN ('.$in.')', ['typ' => (int)$typ] + $id_params);
        }
    }
    header('Location: '.$afile.'.php?name=news'.$refer);
    exit;
}

function conf(): void {
    global $afile, $conf;
    $cfg = $conf['news'] ?? [];
    head();
    $cont = navi(0, 3, 0, 0);
    $cont .= checkPerms('news.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($cfg['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._BASCOL.':</td><td><input type="number" name="bascol" value="'.($cfg['bascol'] ?? 1).'" class="sl_conf" placeholder="'._BASCOL.'" required></td></tr>'
    .'<tr><td>'._C_11.':</td><td><input type="number" name="asocnum" value="'.($cfg['asocnum'] ?? 10).'" class="sl_conf" placeholder="'._C_11.'" required></td></tr>'
    .'<tr><td>'._C_13.':</td><td><input type="number" name="listnum" value="'.($cfg['listnum'] ?? 10).'" class="sl_conf" placeholder="'._C_13.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($cfg['num'] ?? 25).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($cfg['anum'] ?? 25).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.($cfg['nump'] ?? 10).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($cfg['anump'] ?? 10).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._HOMCAT.'</td><td>'.radio_form($cfg['homcat'] ?? 0, 'homcat').'</td></tr>'
    .'<tr><td>'._VIEWCAT.'</td><td>'.radio_form($cfg['viewcat'] ?? 0, 'viewcat').'</td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($cfg['catdesc'] ?? 0, 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($cfg['subcat'] ?? 0, 'subcat').'</td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($cfg['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._C_39.'</td><td>'.radio_form($cfg['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._C_40.'</td><td>'.radio_form($cfg['addquest'] ?? 0, 'addquest').'</td></tr>'
    .'<tr><td>'._C_37.'</td><td>'.radio_form($cfg['autor'] ?? 0, 'autor').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($cfg['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($cfg['read'] ?? 0, 'read').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($cfg['rate'] ?? 0, 'rate').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($cfg['letter'] ?? 0, 'letter').'</td></tr>'
    .'<tr><td>'._C_23.'</td><td>'.radio_form($cfg['assoc'] ?? 0, 'assoc').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="news"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function confsave(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'bascol' => getVar('post', 'bascol', 'num', 1),
        'asocnum' => getVar('post', 'asocnum', 'num', 10),
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
        'assoc' => getVar('post', 'assoc', 'num', 0),
    ];
    setConfigFile('news.php', $cont);
    header('Location: '.$afile.'.php?name=news&op=conf');
    exit;
}

function info(): void {
    head();
    echo navi(0, 4, 0, 0).'<div id="repadm_info">'.adm_info(1, 'news', 0).'</div>';
    foot();
}

switch ($op) {
    default: news(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'admin': admin(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
