<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('forum')) die('Illegal file access');

function forum(): void {
    global $db;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = \'0\', posts = \'0\', lpost = \'0\' WHERE modul = \'forum\'');
    $query = $db->getSqlQuery('SELECT id, parent FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern');
    $cats = [];
    while ([$id, $parent] = $db->getSqlRow($query)) {
        $cats[$id] = [$parent];
    }
    foreach ($cats as $catid => $row) {
        [$topics] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_forum WHERE pid = \'0\' AND cid = :catid', ['catid' => $catid]));
        [$posts] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_forum WHERE pid != \'0\' AND cid = :catid', ['catid' => $catid]));
        [$id, $pid] = $db->getSqlRow($db->getSqlQuery('SELECT id, pid FROM '.PREFIX_DB.'_forum WHERE cid = :catid AND ((pid != \'0\' AND status = \'1\') OR (pid = \'0\' AND status > \'1\')) ORDER BY id DESC LIMIT 1', ['catid' => $catid]));
        $lid = ($pid) ? $pid : ($id ?? 0);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = :topics, posts = :posts, lpost = :lid WHERE id = :catid AND modul = \'forum\'', ['topics' => $topics, 'posts' => $posts, 'lid' => $lid, 'catid' => $catid]);
        $upcat = $row[0];
        while ($upcat != 0) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+:topics, posts = posts+:posts, lpost = :lid WHERE id = :upcat AND modul = \'forum\'', ['topics' => $topics, 'posts' => $posts, 'lid' => $lid, 'upcat' => $upcat]);
            $upcat = (int)($cats[$upcat][0] ?? 0);
        }
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=forum', 'name=forum&amp;op=conf', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO]]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SYNCHIN]);
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._FORUM.'</th><th>'._NEWTOPICS.'</th><th>'._MESSAGES.'</th><th class="{sorter: false}">'._STATUS.'</th></tr></thead><tbody>';
    $query = $db->getSqlQuery('SELECT id, title, intro, status, topics, posts FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern');
    while ([$id, $title, $intro, $state, $topics, $posts] = $db->getSqlRow($query)) {
        $detail = ($intro) ? $intro : _NO;
        $link = title_tip(_DESCRIPTION.': '.$detail).'<a href="index.php?name=forum&amp;cat='.$id.'" target="_blank" title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</a>';
        $cont .= '<tr><td>'.$id.'</td><td>'.$link.'</td><td>'.$topics.'</td><td>'.$posts.'</td><td>'.ad_status('', $state).'</td></tr>';
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=forum', 'name=forum&amp;op=conf', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SYNCHINF]);
    $cont .= checkPerms(CONFIG_DIR.'/forum.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['forum']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._FO_1.':</td><td><input type="number" name="listnum" value="'.($conf['forum']['listnum'] ?? 0).'" class="sl_conf" placeholder="'._FO_1.'" required></td></tr>'
    .'<tr><td>'._FO_2.':</td><td><input type="number" name="pop" value="'.($conf['forum']['pop'] ?? 0).'" class="sl_conf" placeholder="'._FO_2.'" required></td></tr>'
    .'<tr><td>'._COMLETTER.':</td><td><input type="number" name="letter" value="'.($conf['forum']['letter'] ?? 0).'" class="sl_conf" placeholder="'._COMLETTER.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($conf['forum']['num'] ?? 0).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="pnum" value="'.($conf['forum']['pnum'] ?? 0).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._FO_5.':</td><td>'.getcat('forum', $conf['forum']['recycle'] ?? 0, 'recycle', 'sl_conf', '<option value="0">'._NO.'</option>').'</td></tr>'
    .'<tr><td>'._SORT.':</td><td><select name="sort" class="sl_conf">'
    .'<option value="1"';
    if (($conf['forum']['sort'] ?? null) == '1') $cont .= ' selected';
    $cont .= '>'._ASC.'</option>'
    .'<option value="0"';
    if (($conf['forum']['sort'] ?? null) == '0') $cont .= ' selected';
    $cont .= '>'._DESC.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._ALLOWANONPOST.'<div class="sl_small">'._FO_6.'</div></td><td><select name="anonpost" class="sl_conf">'
    .'<option value="0"';
    if (($conf['forum']['anonpost'] ?? null) == '0') $cont .= ' selected';
    $cont .= '>'._APOSTMOD.'</option>'
    .'<option value="1"';
    if (($conf['forum']['anonpost'] ?? null) == '1') $cont .= ' selected';
    $cont .= '>'._APOSTNOMOD.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._FO_7.'</td><td>'.radio_form($conf['forum']['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._FO_8.'</td><td>'.radio_form($conf['forum']['qreply'] ?? 0, 'qreply').'</td></tr>'
    .'<tr><td>'._FO_9.'</td><td>'.radio_form($conf['forum']['ledit'] ?? 0, 'ledit').'</td></tr>'
    .'<tr><td>'._FO_10.'</td><td>'.radio_form($conf['forum']['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._VPRIVAT.'</td><td>'.radio_form($conf['forum']['privat'] ?? 0, 'privat').'</td></tr>'
    .'<tr><td>'._VPROFIL.'</td><td>'.radio_form($conf['forum']['profil'] ?? 0, 'profil').'</td></tr>'
    .'<tr><td>'._VWEB.'</td><td>'.radio_form($conf['forum']['web'] ?? 0, 'web').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="forum"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'pop' => getVar('post', 'pop', 'num', 10),
        'letter' => getVar('post', 'letter', 'num', 0),
        'num' => getVar('post', 'num', 'num', 25),
        'pnum' => getVar('post', 'pnum', 'num', 10),
        'recycle' => getVar('post', 'recycle', 'num', 0),
        'sort' => getVar('post', 'sort', 'num', 1),
        'anonpost' => getVar('post', 'anonpost', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'qreply' => getVar('post', 'qreply', 'num', 0),
        'ledit' => getVar('post', 'ledit', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'privat' => getVar('post', 'privat', 'num', 0),
        'profil' => getVar('post', 'profil', 'num', 0),
        'web' => getVar('post', 'web', 'num', 0),
    ];
    setConfigFile('forum.php', $cont);
    setRedirect($afile.'.php?name=forum&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=forum', 'name=forum&amp;op=conf', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO], 'tab' => 2]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: forum(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}
