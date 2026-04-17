<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function getSearchRow(string $mod, string $afile, int $mid, string $time, int $cid, ?string $ctit, ?string $cdes, ?string $nick, ?string $user, string $edit, bool $post, string $url): array {
    global $tpl;
    $date = $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => format_time($time)]);
    $mlab = $tpl->getHtmlFrag('modul-link', ['url' => 'index.php?name='.$mod, 'title' => _MODUL, 'label' => getModuleName($mod)]);
    $cdes = $cdes ?: $ctit;
    $ctit = $ctit ? $tpl->getHtmlFrag('category-link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$cid, 'title' => cutstr((string)$cdes, 50), 'text' => cutstr($ctit, 15)]) : '';
    $pout = '';
    if ($post) {
        $pval = $nick ? user_info($nick) : ($user ?: _ANONYM);
        $pout = $tpl->getHtmlFrag('media-post-badge', ['title' => _POSTEDBY, 'text' => $pval]);
    }
    $menu = '';
    if (is_moder($mod)) {
        $items = [
            $tpl->getHtmlFrag('comment-action-link', ['href' => $afile.'.php?op='.$edit.'&amp;id='.$mid, 'title' => _FULLEDIT, 'label' => _FULLEDIT, 'class' => '', 'target' => '']),
            $tpl->getHtmlFrag('comment-action-link', ['href' => $url, 'title' => _WINDOWNEW, 'label' => _WINDOWNEW, 'class' => '', 'target' => ' target="_blank"']),
        ];
        $menu = $tpl->getHtmlFrag('editor-action-menu', [
            'editor_label' => _EDITOR,
            'items_html' => implode('', array_map(static fn($item) => $tpl->getHtmlFrag('action-menu-item', ['item_html' => $item]), $items)),
        ]);
    }
    return [$date, $mlab, $ctit, $pout, $menu];
}

function getSearchMods(): array {
    global $conf;
    $mods = [];
    foreach (explode(',', is_array($conf['search']) ? (string)($conf['search']['mods'] ?? '') : '') as $mod) {
        $mod = trim($mod);
        if ($mod === '' || !is_active($mod)) continue;
        $mods[] = $mod;
    }
    return $mods;
}

function getSearchState(): array {
    global $conf;
    $mods = getSearchMods();
    $word = trim(getVar('req', 'word', 'word', ''));
    $mod = getVar('req', 'mod', 'var', '');
    $mod = in_array($mod, $mods, true) ? $mod : '';
    $typ = getVar('req', 'typ', 'num', 0);
    $num = getVar('req', 'num', 'num', 1);
    $stop = ($word !== '' && mb_strlen($word) < (int)$conf['search']['slet']) ? _SEARCHLETMIN.': '.$conf['search']['slet'] : '';
    return [
        'mods' => $mods,
        'word' => $word,
        'mod' => $mod,
        'typ' => $typ,
        'num' => $num,
        'stop' => $stop,
        'lim' => (int)($conf['search']['slimit'] ?? 500),
        'snum' => (int)($conf['search']['snum'] ?? 25),
        'snump' => (int)($conf['search']['snump'] ?? 5),
    ];
}

function getSearchModList(array $mods, string $curr): string {
    global $tpl;
    $cont = '';
    foreach ($mods as $mod) {
        $cont .= $tpl->getHtmlFrag('select-option', ['value_attr' => $mod, 'label_text' => getModuleName($mod), 'is_selected' => $mod === $curr]);
    }
    return $cont;
}

function getSearchTypeList(int $typ): string {
    global $tpl;
    $list = [1 => _MTITLE, 2 => _DESCRIPTION, 3 => _MDIRECTOR, 4 => _MROLES, 5 => _MYEAR];
    $cont = '';
    foreach ($list as $key => $val) {
        $cont .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$key, 'label_text' => $val, 'is_selected' => $key === $typ || (!$typ && $key === 2)]);
    }
    return $cont;
}

function getSearchForm(array $state): string {
    global $conf, $tpl;
    $all_opt = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _SEARCHALL, 'is_selected' => false]);
    $rows = $tpl->getHtmlFrag('form-field-row', [
        'label' => _MODUL.':',
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'mod', 'options_html' => $all_opt.getSearchModList($state['mods'], (string)$state['mod']), 'select_attr' => 'onchange="submit()"']),
    ]);
    if ($state['mod'] === 'media') {
        $rows .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _SEARCHFROM.':',
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'typ', 'options_html' => getSearchTypeList((int)$state['typ'])]),
        ]);
    }
    $rows .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _SEARCH.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'input_attr' => 'maxlength="100" placeholder="'._SEARCH.'" required',
            'input_class' => '',
            'itype' => 'text',
            'name_attr' => 'word',
            'value_attr' => (string)$state['word'],
        ]),
    ]);
    $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => '', 'field_html' => $tpl->getHtmlFrag('form-submit', ['op' => '', 'extra' => '', 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _SEARCH])]);
    return $tpl->getHtmlFrag('forum-reply-form', ['mod_name' => htmlspecialchars($conf['name'], ENT_QUOTES, 'UTF-8'), 'rows_html' => $rows]);
}

function addSearchStat(array $state): void {
    global $db, $conf;
    if (!$conf['search']['asearch'] || $state['word'] === '') return;
    $pars = ['word' => $state['word'], 'modul' => $state['mod']];
    $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_search WHERE word = :word AND modul = :modul', $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_search SET time = NOW(), score = score + 1 WHERE word = :word AND modul = :modul', $pars);
    } else {
        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_search VALUES (NULL, :word, :modul, NOW(), :score)', ['word' => $state['word'], 'modul' => $state['mod'], 'score' => 1]);
    }
}

function getSearchEdit(string $mod): string {
    $list = ['pages' => 'page_add'];
    return $list[$mod] ?? $mod.'_add';
}

function getSearchCols(string $mod): array {
    global $db, $conf;
    $list = [];
    $table = PREFIX_DB.'_'.$mod;
    $result = $db->getSqlQuery(
        'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = :name AND table_name = :table ORDER BY ORDINAL_POSITION',
        ['name' => $conf['db']['name'], 'table' => $table]
    );
    while ($row = $db->getSqlRow($result)) {
        $name = (string)($row[0] ?? $row['COLUMN_NAME'] ?? '');
        if ($name !== '') $list[] = $name;
    }
    return $list;
}

function getSearchSimpleCfg(string $mod): array {
    $cols = getSearchCols($mod);
    $need = ['id', 'title'];
    foreach ($need as $col) {
        if (!in_array($col, $cols, true)) return [];
    }
    $find = [];
    foreach (['title', 'intro', 'body', 'url'] as $col) {
        if (in_array($col, $cols, true)) $find[] = $col;
    }
    if (!$find) return [];
    return [
        'kind' => 'simple',
        'table' => PREFIX_DB.'_'.$mod,
        'find' => $find,
        'edit' => getSearchEdit($mod),
        'hasname' => in_array('name', $cols, true) ? 1 : 0,
        'hastime' => in_array('time', $cols, true) ? 1 : 0,
        'hascid' => in_array('cid', $cols, true) ? 1 : 0,
        'hasuid' => in_array('uid', $cols, true) ? 1 : 0,
        'hasstatus' => in_array('status', $cols, true) ? 1 : 0,
    ];
}

function getSearchMap(): array {
    return [
        'faq' => ['kind' => 'simple', 'sql' => 'SELECT f.id, f.name, f.title, f.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_faq AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :worda OR f.body LIKE :wordb) ORDER BY f.time DESC LIMIT :lim', 'edit' => 'faq_add', 'cnt' => 2],
        'files' => ['kind' => 'simple', 'sql' => 'SELECT f.id, f.name, f.title, f.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :worda OR f.intro LIKE :wordb OR f.body LIKE :wordc) ORDER BY f.time DESC LIMIT :lim', 'edit' => 'files_add', 'cnt' => 3],
        'jokes' => ['kind' => 'simple', 'sql' => 'SELECT j.id, j.name, j.title, j.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id) WHERE j.time <= NOW() AND j.status != \'0\' AND (j.title LIKE :worda OR j.body LIKE :wordb) ORDER BY j.time DESC LIMIT :lim', 'edit' => 'jokes_add', 'cnt' => 2],
        'links' => ['kind' => 'simple', 'sql' => 'SELECT l.id, l.name, l.title, l.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_categories AS c ON (l.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.time <= NOW() AND l.status != \'0\' AND (l.title LIKE :worda OR l.intro LIKE :wordb OR l.body LIKE :wordc OR l.url LIKE :wordd) ORDER BY l.time DESC LIMIT :lim', 'edit' => 'links_add', 'cnt' => 4],
        'news' => ['kind' => 'simple', 'sql' => 'SELECT s.id, s.name, s.title, s.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.time <= NOW() AND s.status != \'0\' AND (s.title LIKE :worda OR s.intro LIKE :wordb OR s.body LIKE :wordc) ORDER BY s.time DESC LIMIT :lim', 'edit' => 'news_add', 'cnt' => 3],
        'pages' => ['kind' => 'simple', 'sql' => 'SELECT p.id, p.name, p.title, p.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE p.time <= NOW() AND p.status != \'0\' AND (p.title LIKE :worda OR p.intro LIKE :wordb OR p.body LIKE :wordc) ORDER BY p.time DESC LIMIT :lim', 'edit' => 'page_add', 'cnt' => 3],
        'auto_links' => ['kind' => 'auto'],
        'forum' => ['kind' => 'forum'],
        'media' => ['kind' => 'media'],
        'shop' => ['kind' => 'shop'],
    ];
}

function getSearchSimple(string $mod, array $cfg, array $state): array {
    global $db, $afile, $tpl;
    $rows = [];
    if (isset($cfg['sql'])) {
        $pars = ['lim' => $state['lim']];
        $keys = ['worda', 'wordb', 'wordc', 'wordd', 'worde'];
        for ($indx = 0; $indx < $cfg['cnt']; $indx++) $pars[$keys[$indx]] = '%'.$state['word'].'%';
        $result = $db->getSqlQuery($cfg['sql'], $pars);
    } else {
        $pars = ['lim' => $state['lim']];
        $cond = [];
        $keys = ['worda', 'wordb', 'wordc', 'wordd', 'worde'];
        foreach ($cfg['find'] as $indx => $col) {
            $key = $keys[$indx];
            $cond[] = 's.'.$col.' LIKE :'.$key;
            $pars[$key] = '%'.$state['word'].'%';
        }
        $sels = [
            's.id',
            $cfg['hasname'] ? 's.name' : 'NULL AS name',
            's.title',
            $cfg['hastime'] ? 's.time' : 'NOW() AS time',
            $cfg['hascid'] ? 'c.id' : '0 AS cid',
            $cfg['hascid'] ? 'c.title' : 'NULL AS ctitle',
            $cfg['hascid'] ? 'c.intro' : 'NULL AS cintro',
            $cfg['hasuid'] ? 'u.name' : 'NULL AS uname',
        ];
        $sql = 'SELECT '.implode(', ', $sels).' FROM '.$cfg['table'].' AS s';
        if ($cfg['hascid']) $sql .= ' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)';
        if ($cfg['hasuid']) $sql .= ' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)';
        $whr = [];
        if ($cfg['hastime']) $whr[] = 's.time <= NOW()';
        if ($cfg['hasstatus']) $whr[] = 's.status != \'0\'';
        $whr[] = '('.implode(' OR ', $cond).')';
        $sql .= ' WHERE '.implode(' AND ', $whr);
        $sql .= $cfg['hastime'] ? ' ORDER BY s.time DESC' : ' ORDER BY s.id DESC';
        $sql .= ' LIMIT :lim';
        $result = $db->getSqlQuery($sql, $pars);
    }
    while ([$mid, $user, $titl, $time, $cid, $ctit, $cdes, $nick] = $db->getSqlRow($result)) {
        $url = ($mod === 'jokes') ? 'index.php?name='.$mod.'&amp;cat='.$cid.'&amp;word='.urlencode($state['word']).'#'.$mid : 'index.php?name='.$mod.'&amp;op=view&amp;id='.$mid.'&amp;word='.urlencode($state['word']);
        $titl = $tpl->getHtmlFrag('link', [
            'href' => $url,
            'title' => $titl,
            'label_html' => filterTextHighlight($titl, $state['word']),
            'suffix_html' => getTplNewGraphic($time),
        ]);
        [$date, $mlab, $clab, $post, $edit] = getSearchRow($mod, $afile, (int)$mid, $time, (int)$cid, $ctit, $cdes, $nick, $user, $cfg['edit'], true, $url);
        $rows[] = ['title' => $titl, 'date' => $date, 'modul' => $mlab, 'ctitle' => $clab, 'post' => $post, 'edit' => $edit];
    }
    return $rows;
}

function getSearchAuto(array $state): array {
    global $db, $afile, $tpl;
    $rows = [];
    $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%', 'wordc' => '%'.$state['word'].'%', 'lim' => $state['lim']];
    $result = $db->getSqlQuery('SELECT id, title, added FROM '.PREFIX_DB.'_auto_links WHERE hits != \'0\' AND (title LIKE :worda OR description LIKE :wordb OR link LIKE :wordc) ORDER BY added DESC LIMIT :lim', $pars);
    while ([$mid, $titl, $time] = $db->getSqlRow($result)) {
        $url = 'index.php?name=auto_links&amp;op=view&amp;id='.$mid;
        $titl = $tpl->getHtmlFrag('link', [
            'href' => $url,
            'title' => $titl,
            'label_html' => filterTextHighlight($titl, $state['word']),
            'suffix_html' => getTplNewGraphic($time),
        ]);
        $date = $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => format_time($time)]);
        $mlab = $tpl->getHtmlFrag('modul-link', ['url' => 'index.php?name=auto_links', 'title' => _MODUL, 'label' => getModuleName('auto_links')]);
        $edit = '';
        if (is_moder('auto_links')) {
            $items = [
                $tpl->getHtmlFrag('comment-action-link', ['href' => $afile.'.php?op=auto_links_add&amp;id='.$mid, 'title' => _FULLEDIT, 'label' => _FULLEDIT, 'class' => '', 'target' => '']),
                $tpl->getHtmlFrag('comment-action-link', ['href' => $url, 'title' => _WINDOWNEW, 'label' => _WINDOWNEW, 'class' => '', 'target' => ' target="_blank"']),
            ];
            $edit = $tpl->getHtmlFrag('editor-action-menu', [
                'editor_label' => _EDITOR,
                'items_html' => implode('', array_map(static fn($item) => $tpl->getHtmlFrag('action-menu-item', ['item_html' => $item]), $items)),
            ]);
        }
        $rows[] = ['title' => $titl, 'date' => $date, 'modul' => $mlab, 'ctitle' => '', 'post' => '', 'edit' => $edit];
    }
    return $rows;
}

function getSearchForum(array $state): array {
    global $db, $afile, $conf, $tpl;
    $rows = [];
    $rid = (int)($conf['forum']['recycle'] ?? 0);
    if (is_moder('forum') || !$rid) {
        $cond = '';
        $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%'];
    } else {
        $cond = 'f.cid != :rid AND';
        $pars = ['rid' => $rid, 'worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%'];
    }
    $pars['lim'] = $state['lim'];
    $result = $db->getSqlQuery('SELECT f.id, f.pid, f.name, f.title, f.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_forum AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE '.$cond.' f.pid = \'0\' AND f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :worda OR f.body LIKE :wordb) ORDER BY f.time DESC LIMIT :lim', $pars);
    while ([$mid, $pid, $user, $titl, $time, $cid, $ctit, $cdes, $nick] = $db->getSqlRow($result)) {
        $tid = !$pid ? $mid : $pid;
        $url = 'index.php?name=forum&amp;op=view&amp;id='.$tid.'&amp;word='.urlencode($state['word']);
        $titl = $tpl->getHtmlFrag('link', [
            'href' => $url,
            'title' => $titl,
            'label_html' => filterTextHighlight($titl, $state['word']),
            'suffix_html' => getTplNewGraphic($time),
        ]);
        [$date, $mlab, $clab, $post, $edit] = getSearchRow('forum', $afile, (int)$tid, $time, (int)$cid, $ctit, $cdes, $nick, $user, 'forum_add', true, $url);
        if (is_moder('forum')) {
            $items = [
                $tpl->getHtmlFrag('comment-action-link', ['href' => 'index.php?name=forum&amp;op=add&amp;cat='.$cid.'&amp;id='.$tid.'&amp;pid='.$pid, 'title' => _FULLEDIT, 'label' => _FULLEDIT, 'class' => '', 'target' => '']),
                $tpl->getHtmlFrag('comment-action-link', ['href' => $url, 'title' => _WINDOWNEW, 'label' => _WINDOWNEW, 'class' => '', 'target' => ' target="_blank"']),
            ];
            $edit = $tpl->getHtmlFrag('editor-action-menu', [
                'editor_label' => _EDITOR,
                'items_html' => implode('', array_map(static fn($item) => $tpl->getHtmlFrag('action-menu-item', ['item_html' => $item]), $items)),
            ]);
        }
        $rows[] = ['title' => $titl, 'date' => $date, 'modul' => $mlab, 'ctitle' => $clab, 'post' => $post, 'edit' => $edit];
    }
    return $rows;
}

function getSearchMedia(array $state): array {
    global $db, $afile, $conf, $tpl;
    $rows = [];
    if ($state['typ'] === 1 && $state['word'] !== '') {
        $cond = '(m.title LIKE :worda OR m.subtitle LIKE :wordb) ORDER BY m.title ASC';
    } elseif ($state['typ'] === 2 && $state['word'] !== '') {
        $cond = '(m.description LIKE :worda) ORDER BY m.description ASC';
    } elseif ($state['typ'] === 3 && $state['word'] !== '') {
        $cond = '(m.director LIKE :worda) ORDER BY m.director ASC';
    } elseif ($state['typ'] === 4 && $state['word'] !== '') {
        $cond = '(m.roles LIKE :worda) ORDER BY m.roles ASC';
    } elseif ($state['typ'] === 5 && $state['word'] !== '') {
        $cond = '(m.year LIKE :worda) ORDER BY m.year ASC';
    } else {
        $cond = '(m.title LIKE :worda OR m.subtitle LIKE :wordb OR m.description LIKE :wordc) ORDER BY m.time DESC';
    }
    $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%', 'wordc' => '%'.$state['word'].'%', 'lim' => $state['lim']];
    $result = $db->getSqlQuery('SELECT m.id, m.name, m.title, m.subtitle, m.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.time <= NOW() AND m.status != \'0\' AND '.$cond.' LIMIT :lim', $pars);
    while ([$mid, $user, $titl, $subt, $time, $cid, $ctit, $cdes, $nick] = $db->getSqlRow($result)) {
        $titl = $subt ? $titl.' '.urldecode($conf['media']['mdefis']).' '.$subt : $titl;
        $url = 'index.php?name=media&amp;op=view&amp;id='.$mid.'&amp;word='.urlencode($state['word']);
        $titl = $tpl->getHtmlFrag('link', [
            'href' => $url,
            'title' => $titl,
            'label_html' => filterTextHighlight($titl, $state['word']),
            'suffix_html' => getTplNewGraphic($time),
        ]);
        [$date, $mlab, $clab, $post, $edit] = getSearchRow('media', $afile, (int)$mid, $time, (int)$cid, $ctit, $cdes, $nick, $user, 'media_add', true, $url);
        $rows[] = ['title' => $titl, 'date' => $date, 'modul' => $mlab, 'ctitle' => $clab, 'post' => $post, 'edit' => $edit];
    }
    return $rows;
}

function getSearchShop(array $state): array {
    global $db, $afile, $tpl;
    $rows = [];
    $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%', 'wordc' => '%'.$state['word'].'%', 'lim' => $state['lim']];
    $result = $db->getSqlQuery('SELECT p.id, p.time, p.title, c.id, c.title, c.intro FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE p.time <= NOW() AND p.status = \'1\' AND (p.title LIKE :worda OR p.intro LIKE :wordb OR p.body LIKE :wordc) ORDER BY p.time DESC LIMIT :lim', $pars);
    while ([$mid, $time, $titl, $cid, $ctit, $cdes] = $db->getSqlRow($result)) {
        $url = 'index.php?name=shop&amp;op=view&amp;id='.$mid.'&amp;word='.urlencode($state['word']);
        $titl = $tpl->getHtmlFrag('link', [
            'href' => $url,
            'title' => $titl,
            'label_html' => filterTextHighlight($titl, $state['word']),
            'suffix_html' => getTplNewGraphic($time),
        ]);
        [$date, $mlab, $clab, $post, $edit] = getSearchRow('shop', $afile, (int)$mid, $time, (int)$cid, $ctit, $cdes, '', '', 'shop_products_add', false, $url);
        $rows[] = ['title' => $titl, 'date' => $date, 'modul' => $mlab, 'ctitle' => $clab, 'post' => $post, 'edit' => $edit];
    }
    return $rows;
}

function getSearchRows(array $state): array {
    $rows = [];
    $list = getSearchMap();
    foreach ($state['mods'] as $mod) {
        if ($state['mod'] !== '' && $state['mod'] !== $mod) continue;
        $cfg = $list[$mod] ?? getSearchSimpleCfg($mod);
        if (!$cfg) continue;
        if ($cfg['kind'] === 'simple') {
            $rows = array_merge($rows, getSearchSimple($mod, $cfg, $state));
        } elseif ($cfg['kind'] === 'auto') {
            $rows = array_merge($rows, getSearchAuto($state));
        } elseif ($cfg['kind'] === 'forum') {
            $rows = array_merge($rows, getSearchForum($state));
        } elseif ($cfg['kind'] === 'media') {
            $rows = array_merge($rows, getSearchMedia($state));
        } elseif ($cfg['kind'] === 'shop') {
            $rows = array_merge($rows, getSearchShop($state));
        }
    }
    return $rows;
}

function getSearchList(array $rows, array $state): string {
    global $conf, $tpl;
    $cont = '';
    $anum = count($rows);
    $from = ($state['num'] - 1) * $state['snum'];
    $slice = array_slice($rows, $from, $state['snum']);
    $numb = $from + 1;
    foreach ($slice as $row) {
        $cont .= $tpl->getHtmlFrag('basic-search', ['n' => $numb, 'title' => $row['title'], 'date' => $row['date'], 'modul' => $row['modul'], 'ctitle' => $row['ctitle'], 'post' => $row['post'], 'admin' => $row['edit']]);
        $numb++;
    }
    if (!$anum) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOMATCHES]);
    $pnum = ceil($anum / $state['snum']);
    $tail = $state['typ'] ? '&typ='.$state['typ'] : '';
    $cont .= ($anum > $state['snum']) ? getPageNumbers('pagenum', $conf['name'], $anum, $pnum, $state['snum'], 'mod='.$state['mod'].'&word='.urlencode($state['word']).$tail.'&', $state['snump']) : $tpl->getHtmlPart('navi-lower', [
        'back_title' => _BACK,
        'back_label' => _BACK,
        'home_href' => 'index.php?name='.$conf['name'],
        'home_title' => _PAGEHOME,
        'home_label' => _PAGEHOME,
        'top_title' => _PAGETOP,
        'top_label' => _PAGETOP,
    ]);
    return $cont;
}

function search(): void {
    global $conf, $tpl;
    $state = getSearchState();
    setHead(['title' => _SEARCH]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _SEARCH]);
    $cont .= getSearchForm($state);
    if (!$state['stop'] && $state['word'] !== '') {
        addSearchStat($state);
        $cont .= getSearchList(getSearchRows($state), $state);
    } else {
        $winfo = ($state['stop']) ? $state['stop'] : _SEARCHINFO;
        $wtyp = ($state['stop']) ? 'warn' : 'info';
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => ($wtyp !== 'info'), 'text' => $winfo]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: search(); break;
}
