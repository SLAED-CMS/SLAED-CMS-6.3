<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('search')) die('Illegal file access');

# The modules of the search, one list for every select, filter and write of this section: its settings, the types with the flag search and the module a row already names
function getSearchModList(string $keep = ''): array {
    global $conf;
    $types = array_keys(array_filter(getNodeTypeMap(), fn(NodeType $v): bool => $v->settings['integrations']['search']));
    $mods = array_merge(explode(',', (string)$conf['search']['mods']), $types, [$keep]);
    return array_values(array_unique(array_filter(array_map('trim', $mods))));
}

function getSearchmodsOpts(string $cmod = '', string $keep = ''): array {
    $opts = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $cmod === '']];
    foreach (getSearchModList($keep) as $mod) $opts[] = ['value_attr' => $mod, 'label_text' => getModuleTitle($mod), 'is_selected' => $cmod === $mod];
    return $opts;
}

function getSearchcurr(): array {
    global $conf;
    $mods = array_filter(array_map('trim', explode(',', (string)$conf['search']['mods'])));
    $mods = array_values(array_unique($mods));
    sort($mods);
    return $mods;
}

# The modules the tab offers: active, open to every visitor and with a page of their own; one that carries no handler modules/<name>/search.php cannot be searched
function getSearchaudit(): array {
    global $conf;
    $curr = getSearchcurr();
    $list = [];
    foreach (array_keys($conf['modules']) as $mod) {
        $mod = (string)$mod;
        if (in_array($mod, ['search', 'node'], true) || !is_active($mod) || !file_exists(BASE_DIR.'/modules/'.$mod.'/index.php')) continue;
        $able = is_file(BASE_DIR.'/modules/'.$mod.'/search.php');
        $list[] = ['mod' => $mod, 'name' => getModuleName($mod), 'state' => !$able ? 'bad' : (in_array($mod, $curr, true) ? 'on' : 'off')];
    }
    usort($list, fn(array $old, array $new): int => strcmp($old['mod'], $new['mod']));
    return $list;
}

# The table of every source of the search, its rows handed over as data; a row names its state for the filter over it, and a module without a handler has no box
function getSearchauditTable(array $list): string {
    global $tpl;
    $head = [
        ['content' => _SOURCE, 'is_truncate' => true],
        ['content' => 'ID', 'is_truncate' => true],
        ['content' => _MODUL, 'is_truncate' => true],
        ['content' => _STATE, 'is_truncate' => true],
        ['is_col_check' => true, 'nosort' => true,
            'content' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck', 'is_check' => true, 'aria_label' => _CHECKALL, 'input_attr' => 'title="'._CHECKALL.'"'])],
    ];
    $tones = ['on' => 'success', 'off' => 'neutral', 'bad' => 'warn'];
    $words = ['on' => _ON, 'off' => _OFF, 'bad' => _SEARCH_NOHANDLER];
    $rows = [];
    foreach ($list as $row) {
        $state = $row['state'];
        $word = $words[$state];
        $box = ['name_attr' => $row['box'], 'value_attr' => $row['mod'], 'is_check' => true, 'is_checked' => $state === 'on', 'aria_label' => $row['name']];
        $rows[] = ['attr' => 'data-sl-row-state="'.$state.'"', 'cells' => [
            ['is_truncate' => true, 'title_text' => $row['name'], 'prefix_html' => $tpl->getHtmlFrag('bootstrap-icon', ['icon_name' => getIconName($row['mod'])]),
                'has_content_text' => true, 'content_text' => $row['name']],
            ['is_truncate' => true, 'title_text' => $row['mod'], 'has_content_text' => true, 'content_text' => $row['mod']],
            ['is_truncate' => true, 'title_text' => $row['type'], 'content_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => $row['tone'], 'label' => $row['type']])],
            ['is_truncate' => true, 'title_text' => $word, 'content_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => $tones[$state], 'label' => $word])],
            ['is_col_check' => true, 'content_html' => ($state === 'bad') ? '' : $tpl->getHtmlFrag('checkbox', $box)],
        ]];
    }
    return $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'attr' => 'id="sl-search-src"', 'head' => $head, 'rows' => $rows]);
}

function getSearchwhere(): array {
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    if ($fmod !== '' && !in_array($fmod, getSearchModList(), true)) $fmod = '';
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

function getSearchnavi(string $sub = '', int $tab = 0, bool $part = false): string {
    return getTplAdminTabs([
        'ops' => ['name=search', 'name=search&op=toplist', 'name=search&op=config', 'name=search&op=delete', 'name=search&op=info'],
        'tabs' => [_HOME, _SEARCHTOP, _PREFERENCES, _DELETE, _MANUAL],
        'tab' => $tab,
        'part' => $part ? [0, 1] : [],
        'subtitle_html' => $sub,
    ]);
}

function getSearchbox(string $type = 'search'): string {
    global $afile, $tpl;
    $sort = getVar('req', 'sort', 'num', ($type === 'toplist') ? 4 : 3);
    $order = getVar('req', 'order', 'num', 2);
    $sorts = [];
    foreach ([1 => _SWORD, 2 => _MODUL, 3 => _DATE, 4 => _HITS] as $key => $val) $sorts[] = ['value_attr' => (string)$key, 'label_text' => $val, 'is_selected' => $sort == $key];
    $hidden = [['nameattr' => 'name', 'valueattr' => 'search']];
    if ($type === 'toplist') $hidden[] = ['nameattr' => 'op', 'valueattr' => 'toplist'];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => $hidden,
        'fields' => [
            ['field_id' => 'sl-search-sort', 'label' => _SORTE, 'name_attr' => 'sort', 'options' => $sorts, 'is_now' => true],
            ['field_id' => 'sl-search-order', 'label' => _SORTORDER, 'name_attr' => 'order', 'is_now' => true, 'options' => [
                ['value_attr' => '1', 'label_text' => _ASC, 'is_selected' => $order == 1], ['value_attr' => '2', 'label_text' => _DESC, 'is_selected' => $order == 2]]],
            ['field_id' => 'sl-search-mod', 'label' => _MODUL, 'name_attr' => 'fmod', 'options' => getSearchmodsOpts(getVar('req', 'fmod', 'var', '')), 'is_now' => true],
            ['field_id' => 'sl-search-find', 'label' => _SWORD, 'name_attr' => 'find', 'itype' => 'search', 'value' => trim(getVar('req', 'find', 'text', ''))],
        ],
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
    $mlab = $mod ? getModuleTitle($mod) : _ALL;
    return $tpl->getHtmlFrag('metric-list', ['items' => [
        ['label' => _SEARCHTOTAL, 'value' => filterDecimal(intval($hits), 0), 'icon' => 'search'],
        ['label' => _SEARCHUNIQUE, 'value' => filterDecimal(intval($uniq), 0), 'icon' => 'fingerprint'],
        ['label' => _SEARCHLAST, 'value' => $last ? format_time((string)$last) : _NO_INFO, 'note' => $last ? format_time((string)$last, 'H:i:s') : ''],
        ['label' => _SEARCHTOP, 'value' => $word ?: _NO_INFO, 'note' => filterDecimal(intval($best), 0), 'icon' => 'trophy'],
        ['label' => _SEARCHTOPMOD, 'value' => $mlab ?: _NO_INFO, 'note' => filterDecimal(intval($mhit), 0), 'icon' => 'grid'],
    ]]);
}

# The search log or, for the top, its words, counted by the same filter its rows are read by and drawn by the list canon
function search(bool $top = false): void {
    global $db, $afile, $conf, $tpl;
    $sort = getVar('req', 'sort', 'num', $top ? 4 : 3);
    $order = getVar('req', 'order', 'num', 2);
    [$where, $pars, $clink, $find, $fmod] = getSearchwhere();
    $sets = [1 => 'word', 2 => 'modul', 3 => 'time', 4 => 'hits'];
    $ordby = ($sets[$sort] ?? ($top ? 'hits' : 'time')).' '.(($order == 1) ? 'ASC' : 'DESC');
    $res = $db->getSqlQuery('SELECT COUNT('.($top ? 'DISTINCT word' : 'id').'), MAX(id) FROM '.PREFIX_DB.'_search'.$where, $pars);
    [$total, $upto] = array_map('intval', $res->fetch(PDO::FETCH_NUM));
    $anum = getListLimit($total);
    $pages = max(1, (int)ceil($total / $anum));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $limit = ' LIMIT '.(($num - 1) * $anum).', '.$anum;
    $query = $top ? 'SELECT SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY time DESC SEPARATOR \',\'), \',\', 1) AS id, word,'
        .' SUBSTRING_INDEX(GROUP_CONCAT(modul ORDER BY time DESC SEPARATOR \',\'), \',\', 1) AS modul, MAX(time) AS time, SUM(IF(score > 0, score, 1)) AS hits'
        .' FROM '.PREFIX_DB.'_search'.$where.' GROUP BY word ORDER BY '.$ordby.$limit
        : 'SELECT id, word, modul, time, IF(score > 0, score, 1) AS hits FROM '.PREFIX_DB.'_search'.$where.' ORDER BY '.$ordby.$limit;
    $result = $db->getSqlQuery($query, $pars);
    setHead();
    $op = $top ? 'toplist' : '';
    $rows = [];
    while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
        $word = (string)$word;
        $mod = trim((string)$mod);
        $mlab = $mod ? getModuleTitle($mod) : _ALL;
        $hword = filterTextHighlight(htmlspecialchars($word, ENT_QUOTES, 'UTF-8'), $find);
        $drop = ['name' => 'search', 'op' => 'drop', 'id[]' => $id, 'sort' => $sort, 'order' => $order, 'num' => $num, 'find' => $find, 'fmod' => $fmod]
            + ($top ? ['top' => 1] : []);
        $tips = [['label' => _MODUL, 'has_value_text' => true, 'value_text' => $mlab], ['label' => _DATE, 'value' => format_time((string)$time, _TIMESTRING)]];
        $rows[] = ['name' => $word, 'details' => $top ? [] : $tips, 'drop' => getTplPostAction($drop, 'trash', _ONDELETE), 'cells' => [
            ['is_truncate' => true, 'title_text' => $word,
                'text_html' => $top ? $tpl->getHtmlFrag('link', ['href' => $afile.'.php?'.getSearchlink(3, 2, 1, $word), 'label_html' => $hword]) : $hword],
            ['is_truncate' => true, 'title_text' => $mlab, 'content_html' => filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find)],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => intval($hits)],
            ['is_col_date' => true, 'moment' => $time],
            ['is_col_actions' => true, 'dial' => [
                ['href' => $afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod, $op).'&op=edit&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT,
                    'is_edit' => true],
            ]],
        ]];
    }
    echo getTplAdminList([
        'head_html' => getSearchnavi(getSearchbox($top ? 'toplist' : 'search'), $top ? 1 : 0, true),
        'note_html' => getSearchsum($where, $pars),
        'is_fixed' => true,
        'key' => $top ? 'search-top' : 'search',
        'head' => [
            ['content' => _SWORD, 'is_truncate' => true],
            ['content' => _MODUL, 'is_truncate' => true],
            ['content' => _HITS, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?name=search'.($top ? '&op=toplist' : '').'&sort='.$sort.'&order='.$order.$clink.'&',
        'page' => $num,
        'pages' => $pages,
        'count' => $total,
        'limit' => $anum,
        'upto' => $upto,
        'reset_url' => ($clink !== '') ? $afile.'.php?name=search'.($top ? '&op=toplist' : '') : '',
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    $hidden = [['nameattr' => 'token', 'valueattr' => getSiteToken('search')]];
    $srcs = [];
    foreach (getNodeTypeMap() as $name => $type) {
        $srcs[] = ['mod' => $name, 'name' => getModuleName($name), 'type' => _NODE, 'tone' => 'info', 'state' => $type->settings['integrations']['search'] ? 'on' : 'off',
            'box' => 'ntype[]'];
        $hidden[] = ['nameattr' => 'ver['.$name.']', 'valueattr' => (string)$type->version];
    }
    foreach (getSearchaudit() as $row) $srcs[] = ['type' => _MODUL, 'tone' => 'accent', 'box' => 'search[]'] + $row;
    usort($srcs, fn(array $old, array $new): int => ($old['state'] === 'bad') <=> ($new['state'] === 'bad'));
    $sums = array_count_values(array_column($srcs, 'state'));
    $picks = [['key' => '', 'label' => _ALL, 'count' => count($srcs), 'is_now' => true]];
    foreach (['on' => _ON, 'off' => _OFF, 'bad' => _SEARCH_NOHANDLER] as $key => $label) $picks[] = ['key' => $key, 'label' => $label, 'count' => $sums[$key] ?? 0];
    setHead();
    $cont = getSearchnavi('', 2);
    $cont .= checkPerms(CONFIG_DIR.'/search.php');
    $cfgrows = [
        [
            'label_html' => _ASEARCH, 'label_id' => $labid = getFieldIds('', 'asearch')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid, 'name' => 'asearch', 'value' => (string)$conf['search']['asearch'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_for' => 'f-slet', 'label_html' => _SEARCHLETMIN, 'hint_html' => _SEARCHLETINFO, 'hint_id' => $hntid = getFieldIds('f-slet')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid, 'itype' => 'number', 'name_attr' => 'slet', 'input_id' => 'f-slet', 'value_attr' => (string)$conf['search']['slet'],
            ]),
        ],
        [
            'label_for' => 'f-slimit', 'label_html' => _SEARCHLIMIT, 'hint_html' => _SEARCHLIMITINFO, 'hint_id' => $hntid = getFieldIds('f-slimit')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid, 'itype' => 'number', 'name_attr' => 'slimit', 'input_id' => 'f-slimit', 'value_attr' => (string)$conf['search']['slimit'],
            ]),
        ],
        ...getTplPagerFields($conf['search']),
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=search&op=save',
        'hidden' => $hidden,
        'content_html' => $tpl->getHtmlFrag('pane', ['icon' => 'sliders', 'title' => _GENPREF, 'content_html' => $tpl->getHtmlPart('div', ['rows' => $cfgrows])])
            .$tpl->getHtmlFrag('row-filter', ['label' => _STATE, 'table_id' => 'sl-search-src', 'items' => $picks]).getSearchauditTable($srcs),
        'submit_label' => _SAVE,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $iswarn = !checkAdminPost('search');
    $text = $iswarn ? _TOKENMISS : _SUCCSAVE;
    if (!$iswarn) {
        $able = array_column(array_filter(getSearchaudit(), fn(array $row): bool => $row['state'] !== 'bad'), 'mod');
        $mods = array_values(array_intersect($able, getVar('post', 'search[]', 'var', [])));
        $picks = getVar('post', 'ntype[]', 'var', []);
        $parts = [];
        foreach (getNodeTypeMap() as $name => $type) $parts[$name] = array_replace($type->settings['integrations'], ['search' => in_array($name, $picks, true)]);
        $fail = updateNodeTypeParts(setConfigFile('search.php', [
            'asearch' => getVar('post', 'asearch', 'num'),
            'mods' => $mods ? implode(',', $mods) : '0',
            'slet' => getVar('post', 'slet', 'num', 3),
            'slimit' => getVar('post', 'slimit', 'num', 500),
            ...getPagerPost(),
        ]), 'integrations', $parts, getVar('post', 'ver[]', '', []));
        if ($fail !== '') [$iswarn, $text] = [true, $fail];
    }
    setRedirect($afile.'.php?name=search&op=config', false, 302, $text, $iswarn);
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
    [$word, $mod, $time, $score] = $db->getSqlRow($result) ?: setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod), false, 302, _NONE_FOUND, true);
    setHead();
    $cont = getSearchnavi(getSearchbox('search'));
    $hits = max(intval($score), 1);
    $rows = [
        [
            'label_for' => 'f-word', 'label_html' => _SWORD,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text', 'name_attr' => 'word', 'input_id' => 'f-word', 'value_attr' => (string)$word, 'placeholder_text' => _SWORD, 'is_required' => true,
            ]),
        ],
        [
            'label_for' => 'f-modul', 'label_html' => _MODUL,
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'modul', 'selectid' => 'f-modul', 'options' => getSearchmodsOpts((string)$mod, (string)$mod)]),
        ],
        ['label_html' => _DATE, 'field_html' => getTplAddDateTime(['name' => 'time', 'label' => _DATE, 'time' => (string)$time, 'with' => true, 'max' => 16])],
        [
            'label_for' => 'f-hits', 'label_html' => _HITS,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'hits', 'input_id' => 'f-hits', 'value_attr' => (string)$hits, 'placeholder_text' => _HITS,
            ]),
        ],
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
        'content_html' => $tpl->getHtmlFrag('pane', ['icon' => 'search', 'title' => _SWORD, 'facts' => [['text' => (string)$word]],
            'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows])]),
        'submit_label' => _SAVE,
    ])]);
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
    $iswarn = !checkAdminPost('search');
    $word = filterCutText(trim(getVar('post', 'word', 'text', '')), 255);
    if (!$iswarn && $word !== '') {
        $mod = strtolower(getVar('post', 'modul', 'var', ''));
        $was = (string)$db->getSqlQuery('SELECT modul FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id])->fetchColumn();
        if (!in_array($mod, getSearchModList($was), true)) $mod = '';
        $time = getVar('post', 'time', 'time');
        $hits = getVar('post', 'hits', 'num', 1);
        $hits = ($hits > 0) ? $hits : 1;
        $done = $db->getSqlQuery(
            'UPDATE '.PREFIX_DB.'_search SET word = :word, modul = :modul, time = :time, score = :score WHERE id = :id',
            ['word' => $word, 'modul' => $mod, 'time' => $time, 'score' => $hits, 'id' => $id]
        ) !== false;
        if ($done) setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod), false, 302, _SUCCSAVE, false);
    }
    $msg = $iswarn ? _TOKENMISS : (($word === '') ? _CERROR : _ERROR);
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod, 'edit').'&id='.$id, false, 302, $msg, true);
}

function delete(): void {
    global $afile, $tpl;
    setHead();
    $cont = getSearchnavi('', 3);
    $modeopts =
        [['value_attr' => 'all', 'label_text' => _SEARCHCLEAR, 'is_selected' => true],
        ['value_attr' => 'mod', 'label_text' => _SEARCHBYMOD],
        ['value_attr' => 'days', 'label_text' => _SEARCHBYDAY],
        ['value_attr' => 'empty', 'label_text' => _SEARCHEMPTY]];
    $rows = [
        [
            'label_for' => 'f-cmod', 'label_html' => _MODUL,
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cmod', 'selectid' => 'f-cmod', 'options' => getSearchmodsOpts('')]),
        ],
        [
            'label_for' => 'f-days', 'label_html' => _DAYS,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'days', 'input_id' => 'f-days', 'value_attr' => '30', 'placeholder_text' => _DAYS]),
        ],
        [
            'label_for' => 'f-mode', 'label_html' => _DELETE,
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'mode', 'selectid' => 'f-mode', 'options' => $modeopts]),
        ],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SEARCHCLEARINFO]).$tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=search&op=clear',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('search')]],
        'content_html' => $tpl->getHtmlFrag('pane', ['icon' => 'trash', 'title' => _DELETE, 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows])]),
        'submit_label' => _DELETE,
        'icon_name' => 'trash',
        'is_danger' => true,
        'button_attr' => 'data-sl-confirm="'.htmlspecialchars(_SEARCHCLEAR.'?', ENT_QUOTES, 'UTF-8').'"',
    ])]);
    echo $cont;
    setFoot();
}

function clear(): void {
    global $db, $afile;
    $iswarn = !checkAdminPost('search');
    $done = false;
    if (!$iswarn) {
        $mode = getVar('post', 'mode', 'var', '');
        $cmod = strtolower(getVar('post', 'cmod', 'var', ''));
        $days = getVar('post', 'days', 'num', 0);
        $sql = match (true) {
            $mode === 'all' => ['DELETE FROM '.PREFIX_DB.'_search WHERE id > :id', ['id' => 0]],
            $mode === 'mod' && in_array($cmod, getSearchModList(), true) => ['DELETE FROM '.PREFIX_DB.'_search WHERE modul = :modul', ['modul' => $cmod]],
            $mode === 'days' && $days > 0 => ['DELETE FROM '.PREFIX_DB.'_search WHERE time < DATE_SUB(NOW(), INTERVAL '.intval($days).' DAY)', []],
            $mode === 'empty' => ['DELETE FROM '.PREFIX_DB.'_search WHERE word = :word OR word IS NULL', ['word' => '']],
            default => null,
        };
        $done = $sql !== null && $db->getSqlQuery(...$sql) !== false;
    }
    setRedirect($afile.'.php?name=search&op=delete', false, 302, $iswarn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

function drop(): void {
    global $db, $afile;
    $ids = getVar('post', 'id[]', 'num');
    $top = getVar('post', 'top', 'num', 0) ? 1 : 0;
    $sort = getVar('post', 'sort', 'num', $top ? 4 : 3);
    $order = getVar('post', 'order', 'num', 2);
    $num = getVar('post', 'num', 'num', 1);
    $find = trim(getVar('post', 'find', 'text', ''));
    $fmod = getVar('post', 'fmod', 'var', '');
    $iswarn = !checkAdminPost('search');
    [$where, $pars] = getSearchwhere();
    $act = static function (int $id) use ($db, $top, $where, $pars): bool {
        $word = $top ? $db->getSqlQuery('SELECT word FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id])->fetchColumn() : false;
        $sql = $top ? 'DELETE FROM '.PREFIX_DB.'_search WHERE word = :word'.str_replace(' WHERE ', ' AND ', $where) : 'DELETE FROM '.PREFIX_DB.'_search WHERE id = :id';
        $res = (!$top || $word !== false) ? $db->getSqlQuery($sql, $top ? ['word' => $word] + $pars : ['id' => $id]) : false;
        return $res && $res->rowCount() > 0;
    };
    $from = $top ? '(SELECT MAX(id) AS id FROM '.PREFIX_DB.'_search'.$where.' GROUP BY word) AS q' : PREFIX_DB.'_search';
    if (!$iswarn && setListPortion($top ? 'q.id' : 'id', $from, $top ? '' : $where, $pars, $act)) exit;
    $done = !$iswarn && $ids;
    foreach ($done ? array_map('intval', $ids) : [] as $id) {
        if (!$act($id)) $done = false;
    }
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod, $top ? 'toplist' : ''), false, 302, $iswarn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=search', 'name=search&op=toplist', 'name=search&op=config', 'name=search&op=delete', 'name=search&op=info'],
        'tabs' => [_HOME, _SEARCHTOP, _PREFERENCES, _DELETE, _MANUAL],
    ]);
}

switch ($op) {
    default: search(); break;
    case 'toplist': search(true); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'delete': delete(); break;
    case 'clear': clear(); break;
    case 'drop': drop(); break;
    case 'info': info(); break;
}
