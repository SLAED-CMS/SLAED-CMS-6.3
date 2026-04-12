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
        $lid = $pid ? $pid : ($id ?? 0);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = :topics, posts = :posts, lpost = :lid WHERE id = :catid AND modul = \'forum\'', ['topics' => $topics, 'posts' => $posts, 'lid' => $lid, 'catid' => $catid]);
        $upcat = $row[0];
        while ($upcat != 0) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+:topics, posts = posts+:posts, lpost = :lid WHERE id = :upcat AND modul = \'forum\'', ['topics' => $topics, 'posts' => $posts, 'lid' => $lid, 'upcat' => $upcat]);
            $upcat = (int)($cats[$upcat][0] ?? 0);
        }
    }
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=forum', 'name=forum&amp;op=config', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO]]);
    $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => false, 'text' => _SYNCHIN]);
    $rows = '';
    $query = $db->getSqlQuery('SELECT id, title, intro, status, topics, posts FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern');
    while ([$id, $title, $intro, $state, $topics, $posts] = $db->getSqlRow($query)) {
        $rows .= $tpl->getHtmlFrag('new/table-row', ['cells_html' => $tpl->getHtmlFrag('new/table-cells', [
            'cells' => [
                ['content_html' => (string)$id],
                ['content_html' => $tpl->getHtmlFrag('new/title-tip', [
                    'items' => [['label' => _DESCRIPTION, 'value' => $intro ?: _NO, 'is_last' => true]],
                    'label_text' => cutstr($title, 60),
                    'title_text' => $title,
                ])],
                ['content_html' => (string)$topics],
                ['content_html' => (string)$posts],
                ['content_html' => ad_status('', $state)],
            ],
        ])]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/table', [
        'is_wrapless' => true,
        'head' => [
            ['content' => _ID],
            ['content' => _FORUM],
            ['content' => _NEWTOPICS],
            ['content' => _MESSAGES],
            ['content' => _STATUS, 'nosort' => true],
        ],
        'rows_html' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $db, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=forum', 'name=forum&amp;op=config', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => false, 'text' => _SYNCHINF]);
    $cont .= checkPerms(CONFIG_DIR.'/forum.php');
    $sortopts =
        $tpl->getHtmlFrag('new/select-option', ['value_attr' => '1', 'label_text' => _ASC, 'is_selected' => ($conf['forum']['sort'] ?? null) == '1']) .
        $tpl->getHtmlFrag('new/select-option', ['value_attr' => '0', 'label_text' => _DESC, 'is_selected' => ($conf['forum']['sort'] ?? null) == '0']);
    $anonopts =
        $tpl->getHtmlFrag('new/select-option', ['value_attr' => '0', 'label_text' => _APOSTMOD, 'is_selected' => ($conf['forum']['anonpost'] ?? null) == '0']) .
        $tpl->getHtmlFrag('new/select-option', ['value_attr' => '1', 'label_text' => _APOSTNOMOD, 'is_selected' => ($conf['forum']['anonpost'] ?? null) == '1']);
    $recycleopts = $tpl->getHtmlFrag('new/select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => !($conf['forum']['recycle'] ?? 0)]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $recycleopts .= $tpl->getHtmlFrag('new/select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)($conf['forum']['recycle'] ?? 0) === (int)$catid,
        ]);
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['forum']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _FO_1, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)($conf['forum']['listnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _FO_2, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'pop', 'value_attr' => (string)($conf['forum']['pop'] ?? 0), 'is_config' => true])],
        ['label_html' => _COMLETTER, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['forum']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['forum']['num'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'pnum', 'value_attr' => (string)($conf['forum']['pnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _FO_5, 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'recycle', 'is_config' => true, 'options_html' => $recycleopts])],
        ['label_html' => _SORT, 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'sort', 'is_config' => true, 'options_html' => $sortopts])],
        ['label_html' => _FO_6, 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'anonpost', 'is_config' => true, 'options_html' => $anonopts])],
        ['label_html' => _FO_7, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['forum']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _FO_8, 'field_html' => getTplRadioGroup(['name' => 'qreply', 'value' => (string)($conf['forum']['qreply'] ?? 0), 'options' => $yesno])],
        ['label_html' => _FO_9, 'field_html' => getTplRadioGroup(['name' => 'ledit', 'value' => (string)($conf['forum']['ledit'] ?? 0), 'options' => $yesno])],
        ['label_html' => _FO_10, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['forum']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VPRIVAT, 'field_html' => getTplRadioGroup(['name' => 'privat', 'value' => (string)($conf['forum']['privat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VPROFIL, 'field_html' => getTplRadioGroup(['name' => 'profil', 'value' => (string)($conf['forum']['profil'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VWEB, 'field_html' => getTplRadioGroup(['name' => 'web', 'value' => (string)($conf['forum']['web'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php?name=forum&amp;op=configsave',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken()]],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
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
    }
    setRedirect($afile.'.php?name=forum&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage(['ops' => ['name=forum', 'name=forum&amp;op=config', 'name=forum&amp;op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _INFO]]);
}

switch ($op) {
    default: forum(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
