<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('forum')) die('Illegal file access');

function forum(): void {
    global $db, $tpl;
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
    $cont = setAdminNavi(['ops' => ['name=forum', 'name=forum&amp;op=config', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO]]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SYNCHIN]);
    $head = '<th>'._ID.'</th><th>'._FORUM.'</th><th>'._NEWTOPICS.'</th><th>'._MESSAGES.'</th><th class="{sorter: false}">'._STATUS.'</th>';
    $rows = '';
    $query = $db->getSqlQuery('SELECT id, title, intro, status, topics, posts FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern');
    while ([$id, $title, $intro, $state, $topics, $posts] = $db->getSqlRow($query)) {
        $detail = ($intro) ? $intro : _NO;
        $link = title_tip(_DESCRIPTION.': '.$detail).'<a href="index.php?name=forum&amp;cat='.$id.'" target="_blank" title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</a>';
        $cols = '<td>'.$id.'</td><td>'.$link.'</td><td>'.$topics.'</td><td>'.$posts.'</td><td>'.ad_status('', $state).'</td>';
        $rows .= getAdminTableRow($cols);
    }
    $cont .= getAdminTable($head, $rows);
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=forum', 'name=forum&amp;op=config', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SYNCHINF]);
    $cont .= checkPerms(CONFIG_DIR.'/forum.php');
    $sort_sel = '<select name="sort" class="sl_conf">'
        .'<option value="1"'.(($conf['forum']['sort'] ?? null) == '1' ? ' selected' : '').'>'._ASC.'</option>'
        .'<option value="0"'.(($conf['forum']['sort'] ?? null) == '0' ? ' selected' : '').'>'._DESC.'</option>'
        .'</select>';
    $anon_sel = '<select name="anonpost" class="sl_conf">'
        .'<option value="0"'.(($conf['forum']['anonpost'] ?? null) == '0' ? ' selected' : '').'>'._APOSTMOD.'</option>'
        .'<option value="1"'.(($conf['forum']['anonpost'] ?? null) == '1' ? ' selected' : '').'>'._APOSTNOMOD.'</option>'
        .'</select>';
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'forum',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['forum']['defis'] ?? ''),
        '_fo1' => _FO_1,
        'listnum' => $conf['forum']['listnum'] ?? 0,
        '_fo2' => _FO_2,
        'pop' => $conf['forum']['pop'] ?? 0,
        '_comletter' => _COMLETTER,
        'letter' => $conf['forum']['letter'] ?? 0,
        '_c33' => _C_33,
        'num' => $conf['forum']['num'] ?? 0,
        '_c35' => _C_35,
        'pnum' => $conf['forum']['pnum'] ?? 0,
        '_fo5' => _FO_5,
        's_recycle' => getcat('forum', $conf['forum']['recycle'] ?? 0, 'recycle', 'sl_conf', '<option value="0">'._NO.'</option>'),
        '_sort' => _SORT,
        's_sort' => $sort_sel,
        '_allowanonpost' => _ALLOWANONPOST,
        '_fo6' => _FO_6,
        's_anonpost' => $anon_sel,
        '_fo7' => _FO_7,
        'r_add' => radio_form($conf['forum']['add'] ?? 0, 'add'),
        '_fo8' => _FO_8,
        'r_qreply' => radio_form($conf['forum']['qreply'] ?? 0, 'qreply'),
        '_fo9' => _FO_9,
        'r_ledit' => radio_form($conf['forum']['ledit'] ?? 0, 'ledit'),
        '_fo10' => _FO_10,
        'r_addmail' => radio_form($conf['forum']['addmail'] ?? 0, 'addmail'),
        '_vprivat' => _VPRIVAT,
        'r_privat' => radio_form($conf['forum']['privat'] ?? 0, 'privat'),
        '_vprofil' => _VPROFIL,
        'r_profil' => radio_form($conf['forum']['profil'] ?? 0, 'profil'),
        '_vweb' => _VWEB,
        'r_web' => radio_form($conf['forum']['web'] ?? 0, 'web'),
        'forum' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
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
    setRedirect($afile.'.php?name=forum&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=forum', 'name=forum&amp;op=config', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO], 'tab' => 2]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: forum(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}

