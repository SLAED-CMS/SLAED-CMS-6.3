<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('forum')) die('Illegal file access');

# Bring the denormalised forum totals back in line with the rows they describe, and answer how much had to be repaired
# The wanted values are computed in full before anything is written, so a forum that is already in order costs no write at all rather than a rewrite of every category
# The reply count of a topic is repaired here too: nothing else ever recounted it, which is why topics could carry a wrong number for years
function updateForumSync(): array {
    global $db;
    $query = $db->getSqlQuery('SELECT id, parent, topics, posts, lpost FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern');
    $cats = [];
    $kids = [];
    while ([$id, $parent, $topics, $posts, $lpost] = $db->getSqlRow($query)) {
        $cats[$id] = ['up' => (int)$parent, 'had' => [(int)$topics, (int)$posts, (int)$lpost], 'want' => [0, 0, 0]];
        $kids[(int)$parent][] = (int)$id;
    }
    foreach ($cats as $catid => $row) {
        [$topics] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_forum WHERE pid = \'0\' AND cid = :catid', ['catid' => $catid]));
        [$posts] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_forum WHERE pid != \'0\' AND cid = :catid', ['catid' => $catid]));
        # Added rather than assigned, so a category listed before its own children still ends up with their totals
        $cats[$catid]['want'][0] += (int)$topics;
        $cats[$catid]['want'][1] += (int)$posts;
        $upcat = $row['up'];
        while ($upcat != 0 && isset($cats[$upcat])) {
            $cats[$upcat]['want'][0] += (int)$topics;
            $cats[$upcat]['want'][1] += (int)$posts;
            $upcat = $cats[$upcat]['up'];
        }
    }
    # The last message is asked of the branch through the same function the delete path uses, so both answer alike
    foreach ($cats as $catid => $row) {
        $sub = [$catid];
        for ($num = 0; $num < count($sub); $num++) {
            foreach ($kids[$sub[$num]] ?? [] as $one) $sub[] = $one;
        }
        $cats[$catid]['want'][2] = getForumLast($sub);
    }
    $done = 0;
    foreach ($cats as $catid => $row) {
        if ($row['had'] === $row['want']) continue;
        $sql = 'UPDATE '.PREFIX_DB.'_categories SET topics = :topics, posts = :posts, lpost = :lid WHERE id = :catid AND modul = \'forum\'';
        if ($db->getSqlQuery($sql, ['topics' => $row['want'][0], 'posts' => $row['want'][1], 'lid' => $row['want'][2], 'catid' => $catid]) !== false) $done++;
    }
    $bent = getForumDrift();
    $fixt = 0;
    foreach ($bent as $tid) {
        if (setForumCount($tid)) $fixt++;
    }
    return ['cats' => $done, 'topics' => $fixt];
}

function forum(): void {
    global $db, $tpl;
    $sync = updateForumSync();
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=forum', 'name=forum&op=config', 'name=forum&op=info'], 'tabs' => [_HOME, _PREFERENCES, _DOCS]]);
    $note = ($sync['cats'] || $sync['topics']) ? sprintf(_SYNCHFIX, $sync['cats'], $sync['topics']) : _SYNCHOK;
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => (bool)($sync['cats'] || $sync['topics']), 'text' => $note]);
    $rows = '';
    $query = $db->getSqlQuery('SELECT id, title, intro, status, topics, posts FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern');
    while ([$id, $title, $intro, $state, $topics, $posts] = $db->getSqlRow($query)) {
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['is_col_id' => true, 'content_html' => (string)$id],
                ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('popover', [
                    'items' => [['label' => _DESCRIPTION, 'value' => $intro ?: _NO, 'is_last' => true]],
                    'label_text' => $title,
                    'title_text' => $title,
                ])],
                ['is_col_count' => true, 'content_html' => (string)$topics],
                ['is_col_count' => true, 'content_html' => (string)$posts],
                ['is_col_status' => true, 'content_html' => ad_status('', $state)],
            ],
        ])]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('table', [
        'is_wrapless' => true,
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _FORUM, 'is_truncate' => true],
            ['content' => _NEWTOPICS, 'is_col_count' => true],
            ['content' => _MESSAGES, 'is_col_count' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
        ],
        'rows_html' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $db, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=forum', 'name=forum&op=config', 'name=forum&op=info'], 'tabs' => [_SYNCH, _PREFERENCES, _DOCS], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SYNCHINF]);
    $cont .= checkPerms(CONFIG_DIR.'/forum.php');
    $sortopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ASC, 'is_selected' => ($conf['forum']['sort'] ?? null) == '1']).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DESC, 'is_selected' => ($conf['forum']['sort'] ?? null) == '0']);
    $anonopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _APOSTMOD, 'is_selected' => ($conf['forum']['anonpost'] ?? null) == '0']).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTNOMOD, 'is_selected' => ($conf['forum']['anonpost'] ?? null) == '1']);
    $recycleopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => !($conf['forum']['recycle'] ?? 0)]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $recycleopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)($conf['forum']['recycle'] ?? 0) === (int)$catid,
        ]);
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['forum']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _FO_1, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)($conf['forum']['listnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _FO_2, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'pop', 'value_attr' => (string)($conf['forum']['pop'] ?? 0), 'is_config' => true])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _COMLETTER, 'hint' => _CONFINES]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'letter', 'value_attr' => (string)($conf['forum']['letter'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['forum']['num'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'pnum', 'value_attr' => (string)($conf['forum']['pnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _FO_5, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'recycle', 'is_config' => true, 'options_html' => $recycleopts])],
        ['label_html' => _SORT, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'sort', 'is_config' => true, 'options_html' => $sortopts])],
        ['label_html' => _FO_6, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'anonpost', 'is_config' => true, 'options_html' => $anonopts])],
        ['label_html' => _FO_7, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['forum']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _FO_8, 'field_html' => getTplRadioGroup(['name' => 'qreply', 'value' => (string)($conf['forum']['qreply'] ?? 0), 'options' => $yesno])],
        ['label_html' => _FO_9, 'field_html' => getTplRadioGroup(['name' => 'ledit', 'value' => (string)($conf['forum']['ledit'] ?? 0), 'options' => $yesno])],
        ['label_html' => _FO_10, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['forum']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VPRIVAT, 'field_html' => getTplRadioGroup(['name' => 'privat', 'value' => (string)($conf['forum']['privat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VPROFIL, 'field_html' => getTplRadioGroup(['name' => 'profil', 'value' => (string)($conf['forum']['profil'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VWEB, 'field_html' => getTplRadioGroup(['name' => 'web', 'value' => (string)($conf['forum']['web'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=forum&op=configsave',
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
    setTplAdminInfoPage([
        'ops' => ['name=forum', 'name=forum&op=config', 'name=forum&op=info'],
        'tabs' => [_SYNCH, _PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: forum(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
