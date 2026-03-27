<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('search')) die('Illegal file access');

function getSearchmods(string $cmod = ''): string {
    global $conf;
    $mods = explode(',', $conf['search']['mods']);
    $opts = getAdminOption('', _ALL);
    foreach ($mods as $mod) {
        $mod = trim($mod);
        if ($mod === '') continue;
        $opts .= getAdminOption($mod, getModuleName($mod), $cmod === $mod);
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

function getSearchauditTable(array $list, string $view = 'enabled'): string {
    global $tpl;
    if (!$list) return $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    $head = $tpl->getHtmlFrag('admin-search-audit-head', [
        'add_label' => _ADD,
        'is_ready' => $view === 'ready',
        'modul_label' => _MODUL,
        'reason_label' => _SEARCHREASON,
        'searchedit_label' => _SEARCHEDIT,
        'searchfields_label' => _SEARCHFIELDS,
        'searchtype_label' => _SEARCHTYPE,
        'table_label' => _TABLE,
    ]);
    $rows = '';
    foreach ($list as $row) {
        $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-search-audit-row', [
            'edit_text' => $row['edit'] ?: _NO,
            'fields_text' => $row['fields'] ?: _NO,
            'is_ready' => $view === 'ready',
            'mod_text' => $row['mod'],
            'mod_value' => $row['mod'],
            'name_text' => $row['name'],
            'reason_text' => $row['reason'] ?: _NO,
            'table_text' => $row['table'],
            'type_text' => $row['type'],
        ]));
    }
    return getAdminTable($head, $rows);
}

function getSearchwhere(): array {
    global $conf;
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $mods = array_map('trim', explode(',', $conf['search']['mods']));
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
    return setAdminNavi([
        'ops' => ['name=search', 'name=search&amp;op=toplist', 'name=search&amp;op=config', 'name=search&amp;op=delete', 'name=search&amp;op=info'],
        'tabs' => [_HOME, _SEARCHTOP, _PREFERENCES, _DELETE, _INFO],
        'sub' => $sub,
        'tab' => $tab,
        'id' => 'search',
    ]);
}

function getSearchbox(string $type = 'search'): string {
    global $afile, $tpl;
    if ($type !== 'search' && $type !== 'toplist') return '';
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $find = trim(getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $sortOpts = '';
    foreach ([1 => _SWORD, 2 => _MODUL, 3 => _DATE, 4 => _HITS] as $key => $val) {
        $sortOpts .= getAdminOption((string)$key, $val, $sort == $key);
    }
    $orderOpts = getAdminOption('1', _ASC, $order == 1).getAdminOption('2', _DESC, $order == 2);
    $ophide = ($type === 'toplist') ? getAdminHidden('op', 'toplist') : '';
    return getAdminSearchBox($tpl->getHtmlFrag('admin-search-box', [
        'action_url' => $afile.'.php',
        'find_placeholder' => _SWORD,
        'find_value' => $find,
        'hidden_html' => getAdminHidden('name', 'search').$ophide,
        'modul_html' => getAdminSelect('fmod', getSearchmods($fmod), '', 'style="width: 140px;"'),
        'modul_label' => _MODUL,
        'ok_label' => _OK,
        'order_html' => getAdminSelect('order', $orderOpts, '', 'style="width: 165px;"'),
        'search_label' => _SEARCH,
        'sort_html' => getAdminSelect('sort', $sortOpts, '', 'style="width: 110px;"'),
        'sort_label' => _SORTE,
    ]));
}

function getSearchsum(string $where, array $pars): string {
    global $db, $tpl;
    [$hits] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_search'.$where, $pars));
    [$uniq] = $db->getSqlRow($db->getSqlQuery('SELECT Count(DISTINCT word) FROM '.PREFIX_DB.'_search'.$where, $pars));
    [$last] = $db->getSqlRow($db->getSqlQuery('SELECT Max(time) FROM '.PREFIX_DB.'_search'.$where, $pars));
    [$word, $best] = $db->getSqlRow($db->getSqlQuery(
        'SELECT word, SUM(IF(score > 0, score, 1)) AS hits FROM '.PREFIX_DB.'_search'.$where
        .' GROUP BY word ORDER BY hits DESC, word ASC LIMIT 1',
        $pars
    )) ?? ['', 0];
    [$mod, $mhit] = $db->getSqlRow($db->getSqlQuery(
        'SELECT modul, SUM(IF(score > 0, score, 1)) AS hits FROM '.PREFIX_DB.'_search'.$where
        .' GROUP BY modul ORDER BY hits DESC, modul ASC LIMIT 1',
        $pars
    )) ?? ['', 0];
    $mlab = $mod ? getModuleName($mod) : _ALL;
    return $tpl->getHtmlFrag('admin-search-summary', [
        'last_label' => _SEARCHLAST,
        'last_text' => $last ? format_time((string)$last, _TIMESTRING) : _NO_INFO,
        'mod_hits' => (string)intval($mhit),
        'mod_label' => _SEARCHTOPMOD,
        'mod_text' => $mlab ?: _NO_INFO,
        'top_hits' => (string)intval($best),
        'top_label' => _SEARCHTOP,
        'top_text' => $word ?: _NO_INFO,
        'total_hits' => (string)intval($hits),
        'total_label' => _SEARCHTOTAL,
        'uniq_hits' => (string)intval($uniq),
        'uniq_label' => _SEARCHUNIQUE,
    ]);
}

function search(): void {
    global $db, $afile, $conf, $tpl;
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    [$where, $pars, $clink, $find, $fmod] = getSearchwhere();
    $anum = intval($conf['search']['anum'] ?? 50);
    $anum = ($anum > 0) ? $anum : 50;
    $anump = intval($conf['search']['anump'] ?? 10);
    $anump = ($anump > 0) ? $anump : 10;
    $sets = [1 => 'word', 2 => 'modul', 3 => 'time', 4 => 'hits'];
    $ordby = $sets[$sort] ?? 'time';
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $page = ($num - 1) * $anum;
    $query = 'SELECT id, word, modul, time, IF(score > 0, score, 1) AS hits FROM '.PREFIX_DB.'_search'.$where
        .' ORDER BY '.$ordby.' '.$ordsc.' LIMIT '.$page.', '.$anum;
    $result = $db->getSqlQuery($query, $pars);
    [$all] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_search'.$where, $pars));
    setHead();
    $cont = getSearchnavi(getSearchbox('search'));
    $cont .= getSearchsum($where, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-search-list-head', [
            'date_label' => _DATE,
            'functions_label' => _FUNCTIONS,
            'hits_label' => _HITS,
            'modul_label' => _MODUL,
            'word_label' => _SWORD,
        ]);
        $rows = '';
        while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
            $show = htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8');
            $mod = trim((string)$mod);
            $mlab = $mod ? getModuleName($mod) : _ALL;
            $mlab = $mlab ?: $mod;
            $hmod = filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find);
            $hword = filterTextHighlight($show, $find);
            $link = getSearchlink($sort, $order, $num, $find, $fmod);
            $drop = getSearchDropForm($id, $afile.'.php?name=search', $sort, $order, $num, $find, $fmod, $show);
            $edit = adminLinkAction($afile.'.php?'.$link.'&amp;op=edit&amp;id='.$id, _FULLEDIT, _FULLEDIT);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-search-list-row', [
                'actions_html' => adminMenuItems([$edit, $drop]),
                'date_text' => format_time((string)$time, _TIMESTRING),
                'hits_text' => (string)intval($hits),
                'modul_html' => $hmod,
                'word_html' => title_tip(_MODUL.': '.htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8').'<br>'._DATE.': '.format_time((string)$time, _TIMESTRING)).$hword,
            ]));
        }
        $html = getAdminTable($head, $rows);
        $pages = ceil($all / $anum);
        $html .= setPageNumbers('pagenum', '', intval($all), intval($pages), $anum, 'name=search&amp;sort='.$sort
            .'&amp;order='.$order.$clink.'&amp;', $anump);
        $cont .= getAdminBox($html);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
    $anum = intval($conf['search']['anum'] ?? 50);
    $anum = ($anum > 0) ? $anum : 50;
    $anump = intval($conf['search']['anump'] ?? 10);
    $anump = ($anump > 0) ? $anump : 10;
    $sets = [1 => 'word', 2 => 'modul', 3 => 'time', 4 => 'hits'];
    $ordby = $sets[$sort] ?? 'hits';
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $page = ($num - 1) * $anum;
    $query = 'SELECT SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY time DESC SEPARATOR \',\'), \',\', 1) AS id, word,'
        .' SUBSTRING_INDEX(GROUP_CONCAT(modul ORDER BY time DESC SEPARATOR \',\'), \',\', 1) AS modul,'
        .' MAX(time) AS time, SUM(IF(score > 0, score, 1)) AS hits FROM '.PREFIX_DB.'_search'.$where
        .' GROUP BY word ORDER BY '.$ordby.' '.$ordsc.' LIMIT '.$page.', '.$anum;
    $result = $db->getSqlQuery($query, $pars);
    [$all] = $db->getSqlRow($db->getSqlQuery('SELECT Count(DISTINCT word) FROM '.PREFIX_DB.'_search'.$where, $pars));
    setHead();
    $cont = getSearchnavi(getSearchbox('toplist'), 1);
    $cont .= getSearchsum($where, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-search-list-head', [
            'date_label' => _DATE,
            'functions_label' => _FUNCTIONS,
            'hits_label' => _HITS,
            'modul_label' => _MODUL,
            'word_label' => _SWORD,
        ]);
        $rows = '';
        while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
            $show = htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8');
            $mod = trim((string)$mod);
            $mlab = $mod ? getModuleName($mod) : _ALL;
            $mlab = $mlab ?: $mod;
            $hmod = filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find);
            $hword = filterTextHighlight($show, $find);
            $link = getSearchlink($sort, $order, $num, $show, $fmod ?? '', 'toplist');
            $drop = getSearchDropForm($id, $afile.'.php?name=search', $sort, $order, $num, $find ?? '', $fmod ?? '', $show);
            $edit = adminLinkAction($afile.'.php?'.$link.'&amp;op=edit&amp;id='.$id, _FULLEDIT, _FULLEDIT);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-search-list-row', [
                'actions_html' => adminMenuItems([$edit, $drop]),
                'date_text' => format_time((string)$time, _TIMESTRING),
                'hits_text' => (string)intval($hits),
                'modul_html' => $hmod,
                'word_html' => $tpl->getHtmlFrag('admin-search-word-link', [
                    'href' => 'admin.php?'.getSearchlink(3, 2, 1, (string)$word, '', ''),
                    'label_html' => $hword,
                ]),
            ]));
        }
        $html = getAdminTable($head, $rows);
        $pages = ceil($all / $anum);
        $html .= setPageNumbers('pagenum', '', intval($all), intval($pages), $anum, 'name=search&amp;op=toplist&amp;sort='
            .$sort.'&amp;order='.$order.$clink.'&amp;', $anump);
        $cont .= getAdminBox($html);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
    $cont = getSearchnavi(getSearchbox('config'), 2);
    if (getVar('get', 'reindex', 'num', 0)) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SEARCHAUTODONE.': '.intval(getVar('get', 'reindex', 'num', 0))]);
    if (getVar('get', 'pick', 'num', 0)) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SEARCHADDSEL.': '.intval(getVar('get', 'pick', 'num', 0))]);
    $cont .= checkPerms(CONFIG_DIR.'/search.php');
    $cfgrows = $tpl->getHtmlFrag('admin-search-config-rows', [
        'anum' => (string)$anum,
        'anump' => (string)$anump,
        'asearch_label' => _ASEARCH,
        'modules_html' => modul('search', 'sl_conf', $conf['search']['mods'], 1, $allow),
        'modules_label_html' => _SMODULE.':<div class="sl_small">'._CTRLINFO.'</div>',
        'radio_html' => radio_form($conf['search']['asearch'], 'asearch'),
        'savechanges_label' => _SAVECHANGES,
        'searchletinfo_label_html' => _SEARCHLETMIN.':<div class="sl_small">'._SEARCHLETINFO.'</div>',
        'searchletmin_label' => _SEARCHLETMIN,
        'searchlimit_label' => _SEARCHLIMIT,
        'searchlimitinfo_label_html' => _SEARCHLIMIT.':<div class="sl_small">'._SEARCHLIMITINFO.'</div>',
        'slimit' => (string)$conf['search']['slimit'],
        'slet' => (string)$conf['search']['slet'],
        'snum' => (string)$conf['search']['snum'],
        'snump' => (string)$conf['search']['snump'],
        'value_anum_label' => _C_34,
        'value_anump_label' => _C_36,
        'value_snum_label' => _SEARCHNUM,
        'value_snump_label' => _C_35,
    ]);
    $cfghide = '<input type="hidden" name="op" value="save"><input type="hidden" name="token" value="'.htmlspecialchars(getSiteToken('search'), ENT_QUOTES, 'UTF-8').'">';
    $html = getAdminForm($afile.'.php?name=search', $cfgrows, $cfghide, 'sl_table_conf');
    $html .= '<h3>'._SEARCHENABLED.'</h3>'.getSearchauditTable($elist, 'enabled');
    $rdyrows = $tpl->getHtmlFrag('admin-search-ready-rows', [
        'searchaddall_label' => _SEARCHADDALL,
        'searchaddsel_label' => _SEARCHADDSEL,
        'searchauto_label_html' => _SEARCHAUTO.':<div class="sl_small">'._SEARCHAUTOINFO.'</div>',
    ]);
    $rdyhide = '<input type="hidden" name="op" value="modadd"><input type="hidden" name="token" value="'.htmlspecialchars(getSiteToken('search'), ENT_QUOTES, 'UTF-8').'">';
    $html .= '<h3>'._SEARCHREADY.'</h3>'.getSearchauditTable($rlist, 'ready').getAdminForm($afile.'.php?name=search', $rdyrows, $rdyhide, 'sl_table_conf');
    $html .= '<h3>'._SEARCHINVALID.'</h3>'.getSearchauditTable($ilist, 'invalid');
    $cont .= getAdminBox($html);
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'search')) {
        setHead();
        $cont = getSearchnavi(getSearchbox('config'), 2);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => 'Security token mismatch']);
        setFoot();
        return;
    }
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
    setRedirect($afile.'.php?name=search&op=config');
}

function reindex(): void {
    global $afile, $conf, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'search')) {
        setHead();
        $cont = getSearchnavi(getSearchbox('config'), 2);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => 'Security token mismatch']);
        setFoot();
        return;
    }
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
    setRedirect($afile.'.php?name=search&op=config&reindex='.(count($mods) - $have));
}

function modadd(): void {
    global $afile, $conf, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'search')) {
        setHead();
        $cont = getSearchnavi(getSearchbox('config'), 2);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => 'Security token mismatch']);
        setFoot();
        return;
    }
    $pick = getVar('post', 'mods', 'raw', []);
    if (!$pick) $pick = getVar('post', 'mods[]', 'raw', []);
    if (is_array($pick)) {
        $pick = array_values(array_filter(array_map('filterVar', $pick), 'strlen'));
    } else {
        $pick = ((string)$pick !== '') ? [filterVar((string)$pick)] : [];
    }
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
    setRedirect($afile.'.php?name=search&op=config&pick='.count($pick));
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
        $rows = $tpl->getHtmlFrag('admin-search-edit-rows', [
            'date_label' => _DATE.':',
            'hits_label' => _HITS.':',
            'hits_placeholder' => _HITS,
            'hits_value' => (string)$hits,
            'modul_html' => getAdminSelect('modul', getSearchmods((string)$mod), 'sl_form'),
            'modul_label' => _MODUL.':',
            'savechanges_label' => _SAVECHANGES,
            'time_html' => datetime(1, 'time', (string)$time, 16, 'sl_form'),
            'word_label' => _SWORD.':',
            'word_placeholder' => _SWORD,
            'word_value' => (string)$word,
        ]);
        $hide = '<input type="hidden" name="op" value="editsave"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="sort" value="'.$sort.'"><input type="hidden" name="order" value="'.$order.'"><input type="hidden" name="num" value="'.$num.'"><input type="hidden" name="find" value="'.htmlspecialchars($find, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="fmod" value="'.htmlspecialchars($fmod, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'.htmlspecialchars(getSiteToken('search'), ENT_QUOTES, 'UTF-8').'">';
        $cont .= getAdminForm($afile.'.php?name=search', $rows, $hide);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function editsave(): void {
    global $db, $afile, $tpl;
    $id = getVar('post', 'id', 'num', 0);
    $sort = getVar('post', 'sort', 'num', 3);
    $order = getVar('post', 'order', 'num', 2);
    $num = getVar('post', 'num', 'num', 1);
    $find = trim(getVar('post', 'find', 'text', ''));
    $fmod = getVar('post', 'fmod', 'var', '');
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'search')) {
        setHead();
        $cont = getSearchnavi(getSearchbox('search'));
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => 'Security token mismatch']);
        setFoot();
        return;
    }
    $word = trim(getVar('post', 'word', 'text', ''));
    $mod = getVar('post', 'modul', 'var', '');
    $time = getVar('post', 'time', 'time');
    $hits = getVar('post', 'hits', 'num', 1);
    $hits = ($hits > 0) ? $hits : 1;
    if ($word === '') {
        setHead();
        $cont = getSearchnavi(getSearchbox('search'));
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SWORD]);
        setFoot();
        return;
    }
    $db->getSqlQuery(
        'UPDATE '.PREFIX_DB.'_search SET word = :word, modul = :modul, time = :time, score = :score WHERE id = :id',
        ['word' => $word, 'modul' => $mod, 'time' => $time, 'score' => $hits, 'id' => $id]
    );
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod));
}

function delete(): void {
    global $afile, $tpl;
    setHead();
    $cont = getSearchnavi(getSearchbox('delete'), 3);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SEARCHCLEARINFO]);
    $modeOpts = getAdminOption('all', _SEARCHCLEAR)
        .getAdminOption('mod', _SEARCHBYMOD)
        .getAdminOption('days', _SEARCHBYDAY)
        .getAdminOption('empty', _SEARCHEMPTY);
    $rows = $tpl->getHtmlFrag('admin-search-delete-rows', [
        'days_label' => _DAYS.':',
        'days_placeholder' => _DAYS,
        'delete_label' => _DELETE,
        'mode_html' => getAdminSelect('mode', $modeOpts, 'sl_form'),
        'modul_html' => getAdminSelect('cmod', getSearchmods(''), 'sl_form'),
        'modul_label' => _MODUL.':',
    ]);
    $hide = '<input type="hidden" name="op" value="clear"><input type="hidden" name="token" value="'.htmlspecialchars(getSiteToken('search'), ENT_QUOTES, 'UTF-8').'">';
    $cont .= getAdminForm($afile.'.php?name=search', $rows, $hide, 'sl_table_conf');
    echo $cont;
    setFoot();
}

function clear(): void {
    global $db, $afile, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'search')) {
        setHead();
        $cont = getSearchnavi(getSearchbox('delete'), 3);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => 'Security token mismatch']);
        setFoot();
        return;
    }
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
    setRedirect($afile.'.php?name=search&op=delete');
}

function drop(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num', 0);
    $sort = getVar('post', 'sort', 'num', 3);
    $order = getVar('post', 'order', 'num', 2);
    $num = getVar('post', 'num', 'num', 1);
    $find = trim(getVar('post', 'find', 'text', ''));
    $fmod = getVar('post', 'fmod', 'var', '');
    if (checkSiteToken(getVar('post', 'token', 'raw', ''), 'search') && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod));
}

function info(): void {
    setHead();
    $cont = getSearchnavi('', 4);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
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
