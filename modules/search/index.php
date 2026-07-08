<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
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

# One-row search panel: module select (+ media type), query input, submit
function getSearchForm(array $state): string {
    global $conf, $tpl;
    $all_opt = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _SEARCHALL, 'is_selected' => false]);
    $mod_html = $tpl->getHtmlFrag('select', [
        'name_attr' => 'mod',
        'options_html' => $all_opt.getSearchModList($state['mods'], (string)$state['mod']),
        'select_attr' => 'onchange="submit()"',
    ]);
    $typ_html = ($state['mod'] === 'media')
        ? $tpl->getHtmlFrag('select', ['name_attr' => 'typ', 'options_html' => getSearchTypeList((int)$state['typ'])])
        : '';
    $word_html = $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'word',
        'value_attr' => (string)$state['word'],
        'maxlength_num' => '100',
        'placeholder_text' => _SEARCH,
        'is_required' => true,
    ]);
    return $tpl->getHtmlFrag('search-form', [
        'action' => 'index.php?name='.$conf['name'],
        'mod_html' => $mod_html,
        'typ_html' => $typ_html,
        'word_html' => $word_html,
        'ok_label' => _SEARCH,
    ]);
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

function getSearchItem(string $mod, string $url, string $edithref, array $data): array {
    return [
        'mod' => $mod, 'url' => $url, 'edithref' => $edithref,
        'title' => (string)($data['title'] ?? ''),
        'time' => (string)($data['time'] ?? ''),
        'cid' => (int)($data['cid'] ?? 0),
        'content' => (string)($data['content'] ?? ''),
        'nick' => $data['nick'] ?? null,
        'user' => $data['user'] ?? null,
        'post' => (bool)($data['post'] ?? false),
        'comments' => isset($data['comments']) && $data['comments'] !== null ? (int)$data['comments'] : null,
        'reads' => isset($data['reads']) && $data['reads'] !== null ? (int)$data['reads'] : null,
    ];
}

function getSearchUrl(array $params, string $word, string $anchor = ''): string {
    $url = getSeoUrl($params);
    if ($word !== '') $url .= (str_contains($url, '?') ? '&' : '?').'word='.urlencode($word);
    return $url.$anchor;
}

function getSimpleCfg(string $mod, array $find, string $content, bool $count = true): array {
    return ['kind' => 'simple', 'table' => PREFIX_DB.'_'.$mod, 'find' => $find, 'content' => $content, 'count' => $count];
}

function getSearchMap(): array {
    return [
        'faq' => getSimpleCfg('faq', ['title', 'body'], 'body'),
        'files' => getSimpleCfg('files', ['title', 'intro', 'body'], 'intro'),
        'jokes' => getSimpleCfg('jokes', ['title', 'body'], 'body', false),
        'links' => getSimpleCfg('links', ['title', 'intro', 'body', 'url'], 'intro'),
        'news' => getSimpleCfg('news', ['title', 'intro', 'body'], 'intro'),
        'pages' => getSimpleCfg('pages', ['title', 'intro', 'body'], 'intro'),
        'auto_links' => ['kind' => 'auto'],
        'forum' => ['kind' => 'forum'],
        'media' => ['kind' => 'media'],
        'shop' => ['kind' => 'shop'],
    ];
}

function getSearchSimple(string $mod, array $cfg, array $state): array {
    global $db, $afile;
    $rows = [];
    $keys = ['worda', 'wordb', 'wordc', 'wordd', 'worde'];
    $pars = ['lim' => $state['lim']];
    $cond = [];
    foreach ($cfg['find'] as $indx => $col) {
        $cond[] = 's.'.$col.' LIKE :'.$keys[$indx];
        $pars[$keys[$indx]] = '%'.$state['word'].'%';
    }
    $cnts = $cfg['count'] ? 's.comments, s.counter' : 'NULL, NULL';
    $sql = 'SELECT s.id, s.name, s.title, s.time, s.cid, s.'.$cfg['content'].', '.$cnts.', u.name'
        .' FROM '.$cfg['table'].' AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        .' WHERE s.time <= NOW() AND s.status != \'0\' AND ('.implode(' OR ', $cond).') ORDER BY s.time DESC LIMIT :lim';
    $result = $db->getSqlQuery($sql, $pars);
    while ([$mid, $user, $titl, $time, $cid, $cont, $comm, $reads, $nick] = $db->getSqlRow($result)) {
        $url = ($mod === 'jokes')
            ? getSearchUrl(['name' => $mod, 'cat' => $cid], $state['word'], '#'.$mid)
            : getSearchUrl(['name' => $mod, 'op' => 'view', 'id' => $mid, 'title' => $titl], $state['word']);
        $rows[] = getSearchItem($mod, $url, $afile.'.php?name='.$mod.'&op=add&id='.$mid, [
            'title' => $titl, 'time' => $time, 'cid' => $cid, 'content' => $cont,
            'nick' => $nick, 'user' => $user, 'post' => true, 'comments' => $comm, 'reads' => $reads,
        ]);
    }
    return $rows;
}

function getSearchAuto(array $state): array {
    global $db, $afile;
    $rows = [];
    $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%', 'wordc' => '%'.$state['word'].'%', 'lim' => $state['lim']];
    $result = $db->getSqlQuery('SELECT id, title, intro, added, hits FROM '.PREFIX_DB.'_auto_links WHERE hits != \'0\' AND (title LIKE :worda OR intro LIKE :wordb OR url LIKE :wordc) ORDER BY added DESC LIMIT :lim', $pars);
    while ([$mid, $titl, $cont, $time, $hits] = $db->getSqlRow($result)) {
        $url = getSearchUrl(['name' => 'auto_links', 'op' => 'view', 'id' => $mid, 'title' => $titl], '');
        $rows[] = getSearchItem('auto_links', $url, $afile.'.php?name=auto_links&op=add&id='.$mid, [
            'title' => $titl, 'time' => $time, 'content' => $cont, 'reads' => $hits,
        ]);
    }
    return $rows;
}

function getSearchForum(array $state): array {
    global $db, $conf;
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
    $result = $db->getSqlQuery('SELECT f.id, f.pid, f.name, f.title, f.time, f.cid, f.body, f.comments, f.counter, u.name FROM '.PREFIX_DB.'_forum AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE '.$cond.' f.pid = \'0\' AND f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :worda OR f.body LIKE :wordb) ORDER BY f.time DESC LIMIT :lim', $pars);
    while ([$mid, $pid, $user, $titl, $time, $cid, $cont, $comm, $reads, $nick] = $db->getSqlRow($result)) {
        $tid = !$pid ? $mid : $pid;
        $url = getSearchUrl(['name' => 'forum', 'op' => 'view', 'id' => $tid, 'title' => $titl], $state['word']);
        $edit = 'index.php?name=forum&op=add&cat='.$cid.'&id='.$tid.'&pid='.$pid;
        $rows[] = getSearchItem('forum', $url, $edit, [
            'title' => $titl, 'time' => $time, 'cid' => $cid, 'content' => $cont,
            'nick' => $nick, 'user' => $user, 'post' => true, 'comments' => $comm, 'reads' => $reads,
        ]);
    }
    return $rows;
}

function getSearchMedia(array $state): array {
    global $db, $afile, $conf;
    $rows = [];
    $cond = match ($state['typ']) {
        1 => '(m.title LIKE :worda OR m.subtitle LIKE :wordb) ORDER BY m.title ASC',
        2 => '(m.intro LIKE :worda) ORDER BY m.intro ASC',
        3 => '(m.director LIKE :worda) ORDER BY m.director ASC',
        4 => '(m.roles LIKE :worda) ORDER BY m.roles ASC',
        5 => '(m.year LIKE :worda) ORDER BY m.year ASC',
        default => '(m.title LIKE :worda OR m.subtitle LIKE :wordb OR m.intro LIKE :wordc) ORDER BY m.time DESC',
    };
    $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%', 'wordc' => '%'.$state['word'].'%', 'lim' => $state['lim']];
    $result = $db->getSqlQuery('SELECT m.id, m.name, m.title, m.subtitle, m.time, m.cid, m.intro, m.comments, m.counter, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.time <= NOW() AND m.status != \'0\' AND '.$cond.' LIMIT :lim', $pars);
    while ([$mid, $user, $titl, $subt, $time, $cid, $cont, $comm, $reads, $nick] = $db->getSqlRow($result)) {
        $titl = $subt ? $titl.' '.urldecode($conf['media']['mdefis']).' '.$subt : $titl;
        $url = getSearchUrl(['name' => 'media', 'op' => 'view', 'id' => $mid, 'title' => $titl], $state['word']);
        $rows[] = getSearchItem('media', $url, $afile.'.php?name=media&op=add&id='.$mid, [
            'title' => $titl, 'time' => $time, 'cid' => $cid, 'content' => $cont,
            'nick' => $nick, 'user' => $user, 'post' => true, 'comments' => $comm, 'reads' => $reads,
        ]);
    }
    return $rows;
}

function getSearchShop(array $state): array {
    global $db, $afile;
    $rows = [];
    $pars = ['worda' => '%'.$state['word'].'%', 'wordb' => '%'.$state['word'].'%', 'wordc' => '%'.$state['word'].'%', 'lim' => $state['lim']];
    $result = $db->getSqlQuery('SELECT p.id, p.time, p.title, p.cid, p.intro, p.comments, p.counter FROM '.PREFIX_DB.'_products AS p WHERE p.time <= NOW() AND p.status = \'1\' AND (p.title LIKE :worda OR p.intro LIKE :wordb OR p.body LIKE :wordc) ORDER BY p.time DESC LIMIT :lim', $pars);
    while ([$mid, $time, $titl, $cid, $cont, $comm, $reads] = $db->getSqlRow($result)) {
        $url = getSearchUrl(['name' => 'shop', 'op' => 'view', 'id' => $mid, 'title' => $titl], $state['word']);
        $rows[] = getSearchItem('shop', $url, $afile.'.php?name=shop&op=productadd&id='.$mid, [
            'title' => $titl, 'time' => $time, 'cid' => $cid, 'content' => $cont, 'comments' => $comm, 'reads' => $reads,
        ]);
    }
    return $rows;
}

function getSearchRows(array $state): array {
    $rows = [];
    $list = getSearchMap();
    foreach ($state['mods'] as $mod) {
        if ($state['mod'] !== '' && $state['mod'] !== $mod) continue;
        $cfg = $list[$mod] ?? null;
        if (!$cfg) continue;
        $rows = array_merge($rows, match ($cfg['kind']) {
            'simple' => getSearchSimple($mod, $cfg, $state),
            'auto' => getSearchAuto($state),
            'forum' => getSearchForum($state),
            'media' => getSearchMedia($state),
            'shop' => getSearchShop($state),
        });
    }
    return $rows;
}

function getSearchSnippet(string $html, string $word, string $mod, int $len = 180): string {
    global $prs;
    if ($html === '') return '';
    $text = html_entity_decode(strip_tags($prs->filterContent($html, false, $mod)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if ($text === '') return '';
    $needle = mb_strtolower(trim($word), 'UTF-8');
    if ($needle !== '') {
        $first = explode(' ', $needle)[0];
        $pos = mb_strpos(mb_strtolower($text, 'UTF-8'), $first, 0, 'UTF-8');
        if ($pos !== false && $pos > 60) $text = '…'.ltrim(mb_substr($text, $pos - 60, null, 'UTF-8'));
    }
    $text = cutstr($text, $len);
    return filterTextHighlight(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), $word);
}

function getSearchLine(array $row, array $state, int $numb): string {
    global $tpl, $conf;
    $word = $state['word'];
    $sep = $conf[$row['mod']]['defis'] ?? '';
    $link = $tpl->getHtmlFrag('link', [
        'href' => $row['url'],
        'title' => $row['title'],
        'label_html' => filterTextHighlight($row['title'], $word),
        'suffix_html' => getTplNewGraphic($row['time']),
    ]);
    $meta = $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($row['time'])), 'title' => _CHNGSTORY, 'text' => format_time($row['time'])]);
    if ($row['comments'] !== null) {
        $meta .= $tpl->getHtmlFrag('link', ['href' => $row['url'].'#comm', 'title' => _COMMENTS, 'label' => (string)$row['comments'], 'is_comments' => true]);
    }
    if ($row['reads'] !== null) {
        $meta .= $tpl->getHtmlFrag('span', ['is_card_reads' => true, 'title' => _READS, 'text' => (string)$row['reads']]);
    }
    if ($row['post']) {
        $pval = $row['nick'] ? user_info($row['nick']) : ($row['user'] ?: _ANONYM);
        $meta .= $tpl->getHtmlFrag('inline-badge', ['title_text' => _POSTEDBY, 'label_html' => $pval, 'is_media_post' => true]);
    }
    $aside = '';
    if ($row['edithref'] !== '' && is_moder($row['mod'])) {
        $items = [
            $tpl->getHtmlFrag('link', ['href' => $row['edithref'], 'title' => _FULLEDIT, 'label' => _FULLEDIT]),
            $tpl->getHtmlFrag('link', ['href' => $row['url'], 'title' => _WINDOWNEW, 'label' => _WINDOWNEW, 'is_blank' => true]),
        ];
        $aside = $tpl->getHtmlFrag('popover', [
            'editor_label' => _EDITOR,
            'items_html' => implode('', array_map(static fn($item) => $tpl->getHtmlFrag('list-item', ['content_html' => $item]), $items)),
        ]);
    }
    $aside .= $tpl->getHtmlFrag('link', ['href' => '#'.$numb, 'title' => (string)$numb, 'label' => (string)$numb, 'is_num_anchor' => true]);
    $snippet = getSearchSnippet($row['content'], $word, $row['mod']);
    $body = $tpl->getHtmlFrag('block-content', ['is_pull_right' => true, 'content' => $aside])
        .$tpl->getHtmlFrag('category-nav', ['crumbs' => getTplCategoryTrail($row['mod'], $row['cid'], $sep, getModuleName($row['mod']))])
        .$tpl->getHtmlFrag('title', ['title_html' => $link])
        .($snippet !== '' ? $tpl->getHtmlFrag('block-content', ['is_snippet' => true, 'content' => $snippet]) : '')
        .$tpl->getHtmlFrag('block-content', ['is_search_meta' => true, 'content' => $meta]);
    return $tpl->getHtmlFrag('block-content', ['id' => (string)$numb, 'is_search_line' => true, 'content' => $body]);
}

function getSearchList(array $rows, array $state): string {
    global $conf, $tpl;
    $cont = '';
    $anum = count($rows);
    $snum = max(1, (int)$state['snum']);
    $pnum = max(1, (int)ceil($anum / $snum));
    $page = min(max(1, (int)$state['num']), $pnum);
    $from = ($page - 1) * $snum;
    $slice = array_slice($rows, $from, $snum);
    $numb = $from + 1;
    foreach ($slice as $row) {
        $cont .= getSearchLine($row, $state, $numb);
        $numb++;
    }
    if (!$anum) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOMATCHES]);
    $tail = $state['typ'] ? '&typ='.$state['typ'] : '';
    $cont .= ($anum > $snum) ? getPageNumbers($conf['name'], $anum, $pnum, $snum, 'mod='.$state['mod'].'&word='.urlencode($state['word']).$tail.'&', $state['snump'], $page) : $tpl->getHtmlPart('navi-lower', [
        'back_button' => ['button_type' => 'button', 'title' => _BACK, 'label' => _BACK, 'is_back' => true, 'is_navi_lower' => true],
        'home_link' => ['href' => 'index.php?name='.$conf['name'], 'title' => _PAGEHOME, 'label' => _PAGEHOME, 'is_navi_lower' => true],
        'top_link' => ['href' => '#top', 'title' => _PAGETOP, 'label' => _PAGETOP, 'is_navi_lower' => true],
    ]);
    return $cont;
}

function search(): void {
    global $conf, $tpl;
    $state = getSearchState();
    setHead(['title' => _SEARCH]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _SEARCH, 'is_level_one' => true]);
    $cont .= getSearchForm($state);
    if (!$state['stop'] && $state['word'] !== '') {
        addSearchStat($state);
        $cont .= getSearchList(getSearchRows($state), $state);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => $state['stop'] !== '', 'text' => $state['stop'] ?: _SEARCHINFO]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: search(); break;
}
