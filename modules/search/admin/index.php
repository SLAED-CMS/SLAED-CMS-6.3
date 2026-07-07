<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('search')) die('Illegal file access');

function getSearchmodsOpts(string $cmod = ''): string {
    global $conf, $tpl;
    $mods = explode(',', (string)$conf['search']['mods']);
    $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $cmod === '']);
    foreach ($mods as $mod) {
        $mod = trim($mod);
        if ($mod === '') continue;
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $mod,
            'label_text' => getModuleName($mod),
            'is_selected' => $cmod === $mod,
        ]);
    }
    return $opts;
}

function getSearchcols(string $mod): array {
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

function getSearchcompat(): array {
    return array_map(fn(array $row) => $row['mod'], getSearchready(getSearchaudit()));
}

function getSearchcurr(): array {
    global $conf;
    $mods = array_filter(array_map('trim', explode(',', (string)$conf['search']['mods'])));
    $mods = array_values(array_unique($mods));
    sort($mods);
    return $mods;
}

function getSearchspec(): array {
    return [
        'auto_links' => ['table' => PREFIX_DB.'_auto_links', 'fields' => 'title, description, link', 'type' => _SEARCHSPECIAL, 'edit' => 'auto_links_add'],
        'forum' => ['table' => PREFIX_DB.'_forum', 'fields' => 'title, body', 'type' => _SEARCHSPECIAL, 'edit' => 'forum_add'],
        'media' => ['table' => PREFIX_DB.'_media', 'fields' => 'title, subtitle, description, director, roles, year', 'type' => _SEARCHSPECIAL, 'edit' => 'media_add'],
        'shop' => ['table' => PREFIX_DB.'_products', 'fields' => 'title, intro, body', 'type' => _SEARCHSPECIAL, 'edit' => 'shop_products_add'],
    ];
}

function getSearchedit(string $mod): string {
    if ($mod === 'pages') return 'page_add';
    return $mod.'_add';
}

function getSearchaudit(): array {
    global $conf;
    $curr = getSearchcurr();
    $spec = getSearchspec();
    $list = [];
    foreach ($conf['modules'] as $mod => $cfg) {
        $mod = (string)$mod;
        if ($mod === 'search' || !is_active($mod) || !file_exists(BASE_DIR.'/modules/'.$mod.'/index.php')) continue;
        $row = [
            'mod' => $mod,
            'name' => getModuleName($mod),
            'enabled' => in_array($mod, $curr, true) ? 1 : 0,
            'type' => '',
            'table' => PREFIX_DB.'_'.$mod,
            'fields' => '',
            'edit' => '',
            'reason' => '',
        ];
        if (isset($spec[$mod])) {
            $row['type'] = $spec[$mod]['type'];
            $row['table'] = $spec[$mod]['table'];
            $row['fields'] = $spec[$mod]['fields'];
            $row['edit'] = $spec[$mod]['edit'];
            $list[] = $row;
            continue;
        }
        $cols = getSearchcols($mod);
        if (!$cols) {
            $row['type'] = _SEARCHINVALID;
            $row['reason'] = _SEARCHNOTABLE;
            $list[] = $row;
            continue;
        }
        $need = ['id', 'title'];
        $miss = [];
        foreach ($need as $col) {
            if (!in_array($col, $cols, true)) $miss[] = $col;
        }
        if ($miss) {
            $row['type'] = _SEARCHINVALID;
            $row['reason'] = _SEARCHMISSCOLS.': '.implode(', ', $miss);
            $list[] = $row;
            continue;
        }
        $find = [];
        foreach (['title', 'intro', 'body', 'url'] as $col) {
            if (in_array($col, $cols, true)) $find[] = $col;
        }
        if (!$find) {
            $row['type'] = _SEARCHINVALID;
            $row['reason'] = _SEARCHNOFIELDS;
            $list[] = $row;
            continue;
        }
        $row['type'] = _SEARCHSIMPLE;
        $row['fields'] = implode(', ', $find);
        $row['edit'] = getSearchedit($mod);
        $list[] = $row;
    }
    usort($list, fn(array $old, array $new) => strcmp($old['mod'], $new['mod']));
    return $list;
}

function getSearchenabled(array $list): array {
    return array_values(array_filter($list, fn(array $row) => (int)$row['enabled'] === 1));
}

function getSearchready(array $list): array {
    return array_values(array_filter($list, fn(array $row) => (int)$row['enabled'] === 0 && $row['type'] !== _SEARCHINVALID));
}

function getSearchinvalid(array $list): array {
    return array_values(array_filter($list, fn(array $row) => (int)$row['enabled'] === 0 && $row['type'] === _SEARCHINVALID));
}

function getSearchsection(string $title, string $html): string {
    return '<h2>'.$title.'</h2>'.$html;
}

function getSearchauditTable(array $list, string $view = 'enabled'): string {
    global $tpl;
    if (!$list) return $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    $head = [
        ['content' => _MODUL, 'is_truncate' => true],
        ['content' => 'ID', 'is_truncate' => true],
        ['content' => _SEARCHTYPE],
        ['content' => _TABLE, 'is_truncate' => true],
        ['content' => _SEARCHFIELDS, 'is_truncate' => true],
        ['content' => _SEARCHEDIT, 'is_truncate' => true],
        ['content' => _SEARCHREASON, 'is_truncate' => true],
    ];
    if ($view === 'ready') $head[] = ['content' => _ADD, 'is_col_check' => true, 'nosort' => true];
    $rows = '';
    foreach ($list as $row) {
        $cells = [
            ['is_truncate' => true, 'title_text' => (string)$row['name'], 'has_content_text' => true, 'content_text' => (string)$row['name']],
            ['is_truncate' => true, 'title_text' => (string)$row['mod'], 'has_content_text' => true, 'content_text' => (string)$row['mod']],
            ['has_content_text' => true, 'content_text' => (string)$row['type']],
            ['is_truncate' => true, 'title_text' => (string)$row['table'], 'has_content_text' => true, 'content_text' => (string)$row['table']],
            ['is_truncate' => true, 'title_text' => (string)($row['fields'] ?: _NO), 'has_content_text' => true, 'content_text' => (string)($row['fields'] ?: _NO)],
            ['is_truncate' => true, 'title_text' => (string)($row['edit'] ?: _NO), 'has_content_text' => true, 'content_text' => (string)($row['edit'] ?: _NO)],
            ['is_truncate' => true, 'title_text' => (string)($row['reason'] ?: _NO), 'has_content_text' => true, 'content_text' => (string)($row['reason'] ?: _NO)],
        ];
        if ($view === 'ready') $cells[] = ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'mods[]', 'value_attr' => (string)$row['mod']])];
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
    }
    return $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows]);
}

function getSearchwhere(): array {
    global $conf;
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $mods = array_map('trim', explode(',', (string)$conf['search']['mods']));
    if ($fmod !== '' && !in_array($fmod, $mods, true)) $fmod = '';
    $cond = [];
    $pars = [];
    $link = '';
    if ($find !== '') {
        $cond[] = 'word LIKE :find';
        $pars['find'] = '%'.$find.'%';
        $link .= '&find='.urlencode($find);
    }
    if ($fmod !== '') {
        $cond[] = 'modul = :fmod';
        $pars['fmod'] = $fmod;
        $link .= '&fmod='.urlencode($fmod);
    }
    $where = $cond ? ' WHERE '.implode(' AND ', $cond) : '';
    return [$where, $pars, $link, $find, $fmod];
}

function getSearchlink(int $sort = 3, int $order = 2, int $num = 1, string $find = '', string $fmod = '', string $op = ''): string {
    $link = 'name=search';
    if ($op !== '' && $op !== 'search') $link .= '&op='.$op;
    $link .= '&sort='.$sort.'&order='.$order.'&num='.$num;
    if ($find !== '') $link .= '&find='.urlencode($find);
    if ($fmod !== '') $link .= '&fmod='.urlencode($fmod);
    return $link;
}

function getSearchnavi(string $sub = '', int $tab = 0): string {
    return getTplAdminTabs([
        'ops' => ['name=search', 'name=search&op=toplist', 'name=search&op=config', 'name=search&op=delete', 'name=search&op=info'],
        'tabs' => [_HOME, _SEARCHTOP, _PREFERENCES, _DELETE, _DOCS],
        'tab' => $tab,
        'subtitle_html' => $sub,
    ]);
}

function getSearchbox(string $type = 'search'): string {
    global $afile, $tpl;
    if ($type !== 'search' && $type !== 'toplist') return '';
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $sortopts = '';
    foreach ([1 => _SWORD, 2 => _MODUL, 3 => _DATE, 4 => _HITS] as $key => $val) {
        $sortopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$key, 'label_text' => $val, 'is_selected' => $sort == $key]);
    }
    $orderopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ASC, 'is_selected' => $order == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _DESC, 'is_selected' => $order == 2]);
    $hidden = $tpl->getHtmlFrag('hidden', ['nameattr' => 'name', 'valueattr' => 'search']);
    if ($type === 'toplist') $hidden .= $tpl->getHtmlFrag('hidden', ['nameattr' => 'op', 'valueattr' => 'toplist']);
    $content = _SORTE.': '
        .$tpl->getHtmlFrag('select', ['name_attr' => 'sort', 'options_html' => $sortopts, 'is_search_sort' => true])
        .' '
        .$tpl->getHtmlFrag('select', ['name_attr' => 'order', 'options_html' => $orderopts, 'is_search_order' => true])
        .' '._SEARCH.': '
        .$tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'find', 'value_attr' => $find, 'placeholder_text' => _SWORD, 'is_search_filter' => true])
        .' '._MODUL.': '
        .$tpl->getHtmlFrag('select', ['name_attr' => 'fmod', 'options_html' => getSearchmodsOpts($fmod), 'is_search_filter' => true])
        .$hidden
        .' '.$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]);
    return $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'get',
            'content_html' => $tpl->getHtmlPart('div', ['is_search_line' => true, 'content_html' => $content]),
    ]);
}

function getSearchsum(string $where, array $pars): string {
    global $db, $tpl;
    [$hits] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_search'.$where, $pars));
    [$uniq] = $db->getSqlRow($db->getSqlQuery('SELECT Count(DISTINCT word) FROM '.PREFIX_DB.'_search'.$where, $pars));
    [$last] = $db->getSqlRow($db->getSqlQuery('SELECT Max(time) FROM '.PREFIX_DB.'_search'.$where, $pars));
    [$word, $best] = $db->getSqlRow($db->getSqlQuery(
        'SELECT word, SUM(IF(score > 0, score, 1)) AS hits FROM '.PREFIX_DB.'_search'.$where.' GROUP BY word ORDER BY hits DESC, word ASC LIMIT 1',
        $pars
    )) ?? ['', 0];
    [$mod, $mhit] = $db->getSqlRow($db->getSqlQuery(
        'SELECT modul, SUM(IF(score > 0, score, 1)) AS hits FROM '.PREFIX_DB.'_search'.$where.' GROUP BY modul ORDER BY hits DESC, modul ASC LIMIT 1',
        $pars
    )) ?? ['', 0];
    $mlab = $mod ? getModuleName($mod) : _ALL;
    return $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', [
        'lines' => [
            _SEARCHTOTAL.': '.intval($hits),
            _SEARCHUNIQUE.': '.intval($uniq),
            _SEARCHLAST.': '.($last ? format_time((string)$last, _TIMESTRING) : _NO_INFO),
            _SEARCHTOP.': '.($word ?: _NO_INFO).' ('.intval($best).')',
            _SEARCHTOPMOD.': '.($mlab ?: _NO_INFO).' ('.intval($mhit).')',
        ],
    ])]);
}

function search(): void {
    global $db, $afile, $conf, $tpl;
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    [$where, $pars, $clink, $find, $fmod] = getSearchwhere();
    $anum = max(intval($conf['search']['anum'] ?? 50), 1);
    $anump = max(intval($conf['search']['anump'] ?? 10), 1);
    $sets = [1 => 'word', 2 => 'modul', 3 => 'time', 4 => 'hits'];
    $ordby = $sets[$sort] ?? 'time';
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $page = ($num - 1) * $anum;
    $query = 'SELECT id, word, modul, time, IF(score > 0, score, 1) AS hits FROM '.PREFIX_DB.'_search'.$where.' ORDER BY '.$ordby.' '.$ordsc.' LIMIT '.$page.', '.$anum;
    $result = $db->getSqlQuery($query, $pars);
    setHead();
    $cont = getSearchnavi(getSearchbox('search'));
    $cont .= getSearchsum($where, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
            $show = htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8');
            $mod = trim((string)$mod);
            $mlab = $mod ? getModuleName($mod) : _ALL;
            $mlab = $mlab ?: $mod;
            $hmod = filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find);
            $hword = filterTextHighlight($show, $find);
            $link = getSearchlink($sort, $order, $num, $find, $fmod);
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                ['is_truncate' => true, 'title_text' => (string)$word, 'content_html' => $tpl->getHtmlFrag('popover', [
                    'items' => [
                        ['label' => _MODUL, 'has_value_text' => true, 'value_text' => $mlab],
                        ['label' => _DATE, 'value' => format_time((string)$time, _TIMESTRING), 'is_last' => true],
                    ],
                    'label_html' => $hword,
                    'title_text' => (string)$word,
                ])],
                ['is_truncate' => true, 'title_text' => $mlab, 'content_html' => $hmod],
                ['is_col_count' => true, 'content_html' => (string)intval($hits)],
                ['is_col_date' => true, 'content_html' => format_time((string)$time, _TIMESTRING)],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => [
                    ['href' => $afile.'.php?'.$link.'&op=edit&id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
                    [
                        'href' => $afile.'.php?op=drop&id='.$id.'&sort='.$sort.'&order='.$order.'&num='.$num.($find !== '' ? '&find='.urlencode($find) : '').($fmod !== '' ? '&fmod='.urlencode($fmod) : '').'&token='.getSiteToken('search'),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes((string)$word).'&quot;?\')"',
                    ],
                ]])],
            ]])]);
        }
        $html = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _SWORD, 'is_truncate' => true],
                ['content' => _MODUL, 'is_truncate' => true],
                ['content' => _HITS, 'is_col_count' => true],
                ['content' => _DATE, 'is_col_date' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $html .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => 'name=search&sort='.$sort.'&order='.$order.$clink.'&', 'table' => '_search', 'field' => 'id']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $html]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function toplist(): void {
    global $db, $afile, $conf, $tpl;
    $sort = getVar('req', 'sort', 'num', 4);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    [$where, $pars, $clink, $find, $fmod] = getSearchwhere();
    $anum = max(intval($conf['search']['anum'] ?? 50), 1);
    $anump = max(intval($conf['search']['anump'] ?? 10), 1);
    $sets = [1 => 'word', 2 => 'modul', 3 => 'time', 4 => 'hits'];
    $ordby = $sets[$sort] ?? 'hits';
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $page = ($num - 1) * $anum;
    $query = 'SELECT SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY time DESC SEPARATOR \',\'), \',\', 1) AS id, word, SUBSTRING_INDEX(GROUP_CONCAT(modul ORDER BY time DESC SEPARATOR \',\'), \',\', 1) AS modul, MAX(time) AS time, SUM(IF(score > 0, score, 1)) AS hits FROM '.PREFIX_DB.'_search'.$where.' GROUP BY word ORDER BY '.$ordby.' '.$ordsc.' LIMIT '.$page.', '.$anum;
    $result = $db->getSqlQuery($query, $pars);
    setHead();
    $cont = getSearchnavi(getSearchbox('toplist'), 1);
    $cont .= getSearchsum($where, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
            $show = htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8');
            $mod = trim((string)$mod);
            $mlab = $mod ? getModuleName($mod) : _ALL;
            $mlab = $mlab ?: $mod;
            $hmod = filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find);
            $hword = filterTextHighlight($show, $find);
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                ['is_truncate' => true, 'title_text' => (string)$word, 'content_html' => $tpl->getHtmlFrag('link', ['href' => 'admin.php?'.getSearchlink(3, 2, 1, (string)$word, '', ''), 'label_html' => $hword])],
                ['is_truncate' => true, 'title_text' => $mlab, 'content_html' => $hmod],
                ['is_col_count' => true, 'content_html' => (string)intval($hits)],
                ['is_col_date' => true, 'content_html' => format_time((string)$time, _TIMESTRING)],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => [
                    ['href' => $afile.'.php?'.getSearchlink($sort, $order, $num, $show, $fmod ?? '', 'toplist').'&op=edit&id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
                    [
                        'href' => $afile.'.php?op=drop&id='.$id.'&sort='.$sort.'&order='.$order.'&num='.$num.($find !== '' ? '&find='.urlencode($find) : '').($fmod !== '' ? '&fmod='.urlencode($fmod) : '').'&token='.getSiteToken('search'),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes((string)$word).'&quot;?\')"',
                    ],
                ]])],
            ]])]);
        }
        $html = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _SWORD, 'is_truncate' => true],
                ['content' => _MODUL, 'is_truncate' => true],
                ['content' => _HITS, 'is_col_count' => true],
                ['content' => _DATE, 'is_col_date' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $html .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => 'name=search&op=toplist&sort='.$sort.'&order='.$order.$clink.'&', 'table' => '_search', 'field' => 'id']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $html]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    $allow = ['auto_links', 'faq', 'files', 'forum', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    $anum = intval($conf['search']['anum'] ?? 50);
    $anump = intval($conf['search']['anump'] ?? 10);
    $audit = getSearchaudit();
    $elist = getSearchenabled($audit);
    $rlist = getSearchready($audit);
    $ilist = getSearchinvalid($audit);
    setHead();
    $cont = getSearchnavi('', 2);
    if (getVar('get', 'reindex', 'num', 0)) $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SEARCHAUTODONE.': '.intval(getVar('get', 'reindex', 'num', 0))])]);
    if (getVar('get', 'pick', 'num', 0)) $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SEARCHADDSEL.': '.intval(getVar('get', 'pick', 'num', 0))])]);
    $cont .= checkPerms(CONFIG_DIR.'/search.php');
    $modshtml = '';
    $curr = getSearchcurr();
    foreach (scandir('modules') as $file) {
        if (str_contains($file, '.')) continue;
        if ($allow && !in_array($file, $allow, true)) continue;
        $modshtml .= $tpl->getHtmlFrag('checkbox', [
            'is_right' => true,
            'name_attr' => 'search[]',
            'value_attr' => $file,
            'is_checked' => in_array($file, $curr, true),
            'label_text' => getModuleName($file),
            'code_text' => $file,
        ]);
    }
    $cfgrows = [
        ['label_html' => _ASEARCH, 'field_html' => getTplRadioGroup(['name' => 'asearch', 'value' => (string)$conf['search']['asearch'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SMODULE, 'hint' => _CTRLINFO]), 'field_html' => $modshtml, 'is_full' => true],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SEARCHLETMIN, 'hint' => _SEARCHLETINFO]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'slet', 'value_attr' => (string)$conf['search']['slet']])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SEARCHLIMIT, 'hint' => _SEARCHLIMITINFO]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'slimit', 'value_attr' => (string)$conf['search']['slimit']])],
        ['label_html' => _SEARCHNUM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'snum', 'value_attr' => (string)$conf['search']['snum']])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'snump', 'value_attr' => (string)$conf['search']['snump']])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$anum])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$anump])],
    ];
    $html = $tpl->getHtmlPart('box', ['content_html' => getSearchsection(_PREFERENCES, $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=search&op=save',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('search')]],
        'rows' => $cfgrows,
        'submit_label' => _SAVECHANGES,
    ]))]);
    $html .= $tpl->getHtmlPart('box', ['content_html' => getSearchsection(_SEARCHENABLED, getSearchauditTable($elist, 'enabled'))]);
    $readyform = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=search&op=modadd',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('search')]],
        'content_html' => getSearchauditTable($rlist, 'ready'),
        'actions_html' => $tpl->getHtmlFrag('checkbox', [
            'is_right' => true,
            'name_attr' => 'all',
            'value_attr' => '1',
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SEARCHADDALL, 'hint' => _SEARCHAUTOINFO]),
        ]),
        'submit_label' => _SEARCHADDSEL,
    ]);
    $html .= $tpl->getHtmlPart('box', ['content_html' => getSearchsection(_SEARCHREADY, $readyform)]);
    $html .= $tpl->getHtmlPart('box', ['content_html' => getSearchsection(_SEARCHINVALID, getSearchauditTable($ilist, 'invalid'))]);
    $reform = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=search&op=reindex',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('search')]],
        'submit_label' => _SEARCHAUTO,
    ]);
    $html .= $tpl->getHtmlPart('box', ['content_html' => $reform]);
    $cont .= $html;
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $iswarn = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'search');
    if (!$iswarn) {
        $mods = getVar('post', 'search[]', 'var', []);
        setConfigFile('search.php', [
            'asearch' => getVar('post', 'asearch', 'num'),
            'mods' => $mods ? implode(',', $mods) : '0',
            'slet' => getVar('post', 'slet', 'num', 3),
            'slimit' => getVar('post', 'slimit', 'num', 500),
            'snum' => getVar('post', 'snum', 'num', 25),
            'snump' => getVar('post', 'snump', 'num', 5),
            'anum' => getVar('post', 'anum', 'num', 50),
            'anump' => getVar('post', 'anump', 'num', 10),
        ]);
    }
    setRedirect($afile.'.php?name=search&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function reindex(): void {
    global $afile, $conf;
    $iswarn = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'search');
    $count = 0;
    if (!$iswarn) {
        $curr = array_filter(array_map('trim', explode(',', (string)$conf['search']['mods'])));
        $have = count($curr);
        $mods = array_values(array_unique(array_merge($curr, getSearchcompat())));
        sort($mods);
        setConfigFile('search.php', [
            'asearch' => $conf['search']['asearch'],
            'mods' => $mods ? implode(',', $mods) : '0',
            'slet' => $conf['search']['slet'],
            'slimit' => $conf['search']['slimit'],
            'snum' => $conf['search']['snum'],
            'snump' => $conf['search']['snump'],
            'anum' => $conf['search']['anum'] ?? 50,
            'anump' => $conf['search']['anump'] ?? 10,
        ]);
        $count = count($mods) - $have;
    }
    setRedirect($afile.'.php?name=search&op=config'.(!$iswarn ? '&reindex='.$count : ''), false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function modadd(): void {
    global $afile, $conf;
    $iswarn = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'search');
    $count = 0;
    if (!$iswarn) {
        $pick = getVar('post', 'mods', 'raw', []);
        if (!$pick) $pick = getVar('post', 'mods[]', 'raw', []);
        if (is_array($pick)) $pick = array_values(array_filter(array_map('filterVar', $pick), 'strlen'));
        else $pick = ((string)$pick !== '') ? [filterVar((string)$pick)] : [];
        $all = getVar('post', 'all', 'num', 0);
        $curr = getSearchcurr();
        $ready = array_map(fn(array $row) => $row['mod'], getSearchready(getSearchaudit()));
        $pick = $all ? $ready : array_values(array_intersect($ready, $pick));
        $mods = array_values(array_unique(array_merge($curr, $pick)));
        sort($mods);
        setConfigFile('search.php', [
            'asearch' => $conf['search']['asearch'],
            'mods' => $mods ? implode(',', $mods) : '0',
            'slet' => $conf['search']['slet'],
            'slimit' => $conf['search']['slimit'],
            'snum' => $conf['search']['snum'],
            'snump' => $conf['search']['snump'],
            'anum' => $conf['search']['anum'] ?? 50,
            'anump' => $conf['search']['anump'] ?? 10,
        ]);
        $count = count($pick);
    }
    setRedirect($afile.'.php?name=search&op=config'.(!$iswarn ? '&pick='.$count : ''), false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function edit(): void {
    global $db, $afile, $tpl;
    $id = getVar('get', 'id', 'num', 0);
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $result = $db->getSqlQuery('SELECT word, modul, time, score FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id]);
    setHead();
    $cont = getSearchnavi(getSearchbox('search'));
    if ($db->getSqlRowCount($result) > 0) {
        [$word, $mod, $time, $score] = $db->getSqlRow($result);
        $hits = max(intval($score), 1);
        $rows = [
            ['label_html' => _SWORD, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'word', 'value_attr' => (string)$word, 'placeholder_text' => _SWORD])],
            ['label_html' => _MODUL, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'modul', 'options_html' => getSearchmodsOpts((string)$mod)])],
            ['label_html' => _DATE, 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => (string)$time, 'with' => true, 'max' => 16])],
            ['label_html' => _HITS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'hits', 'value_attr' => (string)$hits, 'placeholder_text' => _HITS])],
        ];
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=search&op=editsave',
            'hidden' => [
                ['nameattr' => 'id', 'valueattr' => (string)$id],
                ['nameattr' => 'sort', 'valueattr' => (string)$sort],
                ['nameattr' => 'order', 'valueattr' => (string)$order],
                ['nameattr' => 'num', 'valueattr' => (string)$num],
                ['nameattr' => 'find', 'valueattr' => $find],
                ['nameattr' => 'fmod', 'valueattr' => $fmod],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('search')],
            ],
            'rows' => $rows,
            'submit_label' => _SAVECHANGES,
        ])]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function editsave(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num', 0);
    $sort = getVar('post', 'sort', 'num', 3);
    $order = getVar('post', 'order', 'num', 2);
    $num = getVar('post', 'num', 'num', 1);
    $find = trim(getVar('post', 'find', 'text', ''));
    $fmod = getVar('post', 'fmod', 'var', '');
    $iswarn = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'search');
    $word = trim(getVar('post', 'word', 'text', ''));
    if (!$iswarn && $word !== '') {
        $mod = getVar('post', 'modul', 'var', '');
        $time = getVar('post', 'time', 'time');
        $hits = getVar('post', 'hits', 'num', 1);
        $hits = ($hits > 0) ? $hits : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_search SET word = :word, modul = :modul, time = :time, score = :score WHERE id = :id', ['word' => $word, 'modul' => $mod, 'time' => $time, 'score' => $hits, 'id' => $id]);
        setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod), false, 302, _SUCCSAVE, false);
        return;
    }
    $msg = $iswarn ? _TOKENMISS : _SWORD;
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod, 'edit').'&id='.$id, false, 302, $msg, true);
}

function delete(): void {
    global $afile, $tpl;
    setHead();
    $cont = getSearchnavi('', 3);
    $modeopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => 'all', 'label_text' => _SEARCHCLEAR, 'is_selected' => true])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'mod', 'label_text' => _SEARCHBYMOD])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'days', 'label_text' => _SEARCHBYDAY])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'empty', 'label_text' => _SEARCHEMPTY]);
    $rows = [
        ['label_html' => _MODUL, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cmod', 'options_html' => getSearchmodsOpts('')])],
        ['label_html' => _DAYS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'days', 'value_attr' => '30', 'placeholder_text' => _DAYS])],
        ['label_html' => _DELETE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'mode', 'options_html' => $modeopts])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SEARCHCLEARINFO]).$tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=search&op=clear',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('search')]],
        'rows' => $rows,
        'submit_label' => _DELETE,
    ])]);
    echo $cont;
    setFoot();
}

function clear(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'search');
    if (!$iswarn) {
        $mode = getVar('post', 'mode', 'var', 'all');
        $cmod = getVar('post', 'cmod', 'var', '');
        $days = getVar('post', 'days', 'num', 30);
        if ($mode === 'mod' && $cmod !== '') {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE modul = :modul', ['modul' => $cmod]);
        } elseif ($mode === 'days' && $days > 0) {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE time < DATE_SUB(NOW(), INTERVAL '.intval($days).' DAY)');
        } elseif ($mode === 'empty') {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE word = \'\' OR word IS NULL');
        } else {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE id > :id', ['id' => 0]);
        }
    }
    setRedirect($afile.'.php?name=search&op=delete', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function drop(): void {
    global $db, $afile;
    $id = getVar('req', 'id', 'num', 0);
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('req', 'num', 'num', 1);
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $iswarn = !checkSiteToken(getVar('req', 'token', 'raw', ''), 'search');
    if (!$iswarn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod), false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=search', 'name=search&op=toplist', 'name=search&op=config', 'name=search&op=delete', 'name=search&op=info'],
        'tabs' => [_HOME, _SEARCHTOP, _PREFERENCES, _DELETE, _DOCS],
    ]);
}

switch ($op) {
    default: search(); break;
    case 'toplist': toplist(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'reindex': reindex(); break;
    case 'modadd': modadd(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'delete': delete(); break;
    case 'clear': clear(); break;
    case 'drop': drop(); break;
    case 'info': info(); break;
}
