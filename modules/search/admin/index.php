<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('search')) die('Illegal file access');

function getSearchtoken(): string {
    static $token = '';
    if ($token === '') $token = hash('sha256', session_id().'|search-admin');
    return $token;
}

function checkSearchtoken(): bool {
    return hash_equals(getSearchtoken(), getVar('post', 'token', 'raw', ''));
}

function getSearchmods(string $cmod = ''): string {
    global $conf;
    $mods = explode(',', $conf['search']['mods']);
    $cont = '<option value="">'. _ALL .'</option>';
    foreach ($mods as $mod) {
        $mod = trim($mod);
        if ($mod === '') continue;
        $sel = ($cmod === $mod) ? ' selected' : '';
        $cont .= '<option value="'.$mod.'"'.$sel.'>'.getModuleName($mod).'</option>';
    }
    return $cont;
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
    if (!$list) return setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    $pick = ($view === 'ready') ? '<th class="{sorter: false}">'._ADD.'</th>' : '';
    $cont = '<table class="sl_table_list_sort"><thead><tr>'.$pick.'<th>'._MODUL.'</th><th>'._SEARCHTYPE.'</th><th>'._TABLE.'</th><th>'._SEARCHFIELDS.'</th><th>'._SEARCHEDIT.'</th><th>'._SEARCHREASON.'</th></tr></thead><tbody>';
    foreach ($list as $row) {
        $mark = ($view === 'ready') ? '<td class="sl_center"><input type="checkbox" name="mods[]" value="'.$row['mod'].'"></td>' : '';
        $cont .= '<tr>'.$mark.'<td>'.$row['name'].'<div class="sl_small">'.$row['mod'].'</div></td><td>'.$row['type'].'</td><td>'.$row['table'].'</td><td>'.($row['fields'] ?: _NO).'</td><td>'.($row['edit'] ?: _NO).'</td><td>'.($row['reason'] ?: _NO).'</td></tr>';
    }
    $cont .= '</tbody></table>';
    return $cont;
}

function getSearchwhere(): array {
    global $conf;
    $find = trim((string)getVar('req', 'find', 'text', ''));
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

function getSearchbox(string $view = 'search'): string {
    global $afile;
    if ($view !== 'search' && $view !== 'top') return '';
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $find = trim((string)getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $box = '<form method="post" action="'.$afile.'.php"><span style="white-space: nowrap;">'._SORTE.': <select name="sort" style="width: 110px;">';
    $list = [1 => _SWORD, 2 => _MODUL, 3 => _DATE, 4 => _HITS];
    foreach ($list as $key => $val) {
        $sel = ($sort == $key) ? ' selected' : '';
        $box .= '<option value="'.$key.'"'.$sel.'>'.$val.'</option>';
    }
    $box .= '</select> <select name="order" style="width: 165px;">';
    $list = [1 => _ASC, 2 => _DESC];
    foreach ($list as $key => $val) {
        $sel = ($order == $key) ? ' selected' : '';
        $box .= '<option value="'.$key.'"'.$sel.'>'.$val.'</option>';
    }
    $box .= '</select> '._SEARCH.': <input type="text" name="find" value="'.htmlspecialchars($find, ENT_QUOTES, 'UTF-8')
        .'" class="sl_form" style="width: 140px;" placeholder="'._SWORD.'"> '._MODUL.': <select name="fmod" style="width: 140px;">'.getSearchmods($fmod).'</select>'
        .'<input type="hidden" name="name" value="search">';
    if ($view === 'top') $box .= '<input type="hidden" name="op" value="top">';
    $box .= ' <input type="submit" value="'._OK.'" class="sl_but_blue"></span></form>';
    return setTemplateBasic('searchbox', ['{%searchbox%}' => $box]);
}

function getSearchsum(string $where, array $pars): string {
    global $db;
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
    $text = _SEARCHTOTAL.': '.intval($hits)
        .'<br>'._SEARCHUNIQUE.': '.intval($uniq)
        .'<br>'._SEARCHLAST.': '.($last ? format_time((string)$last, _TIMESTRING) : _NO_INFO)
        .'<br>'._SEARCHTOP.': '.($word ?: _NO_INFO).' ('.intval($best).')'
        .'<br>'._SEARCHTOPMOD.': '.($mlab ?: _NO_INFO).' ('.intval($mhit).')';
    return setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $text]);
}

function navi(int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = '', string $view = 'search'): string {
    $ops = ['name=search', 'name=search&amp;op=top', 'name=search&amp;op=conf', 'name=search&amp;op=del', 'name=search&amp;op=info'];
    $lang = [_HOME, _SEARCHTOP, _PREFERENCES, _DELETE, _INFO];
    return getAdminTabs(getSearchbox($view), $ops, $lang, [], [], $tab, $subtab, $legacy, $id);
}

function search(): void {
    global $db, $afile, $conf;
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
    $cont = navi(0, 0, 0, 'search', 'search');
    $cont .= getSearchsum($where, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._SWORD.'</th><th>'._MODUL.'</th><th>'._HITS
            .'</th><th>'._DATE.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
            $show = htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8');
            $mod = trim((string)$mod);
            $mlab = $mod ? getModuleName($mod) : _ALL;
            $mlab = $mlab ?: $mod;
            $hmod = filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find);
            $hword = filterTextHighlight($show, $find);
            $link = getSearchlink($sort, $order, $num, $find, $fmod);
            $drop = '<form id="drop'.$id.'" action="'.$afile.'.php?name=search" method="post" style="display:none">'
                .'<input type="hidden" name="op" value="drop"><input type="hidden" name="id" value="'.$id.'">'
                .'<input type="hidden" name="sort" value="'.$sort.'"><input type="hidden" name="order" value="'.$order.'">'
                .'<input type="hidden" name="num" value="'.$num.'"><input type="hidden" name="find" value="'
                .htmlspecialchars($find, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="fmod" value="'
                .htmlspecialchars($fmod, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'
                .htmlspecialchars(getSearchtoken(), ENT_QUOTES, 'UTF-8').'"></form>';
            $edit = '<a href="'.$afile.'.php?'.$link.'&amp;op=edit&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>';
            $drop .= '<a href="#" OnClick="if (DelCheck(this, \''._DELETE.' &quot;'.$show.'&quot;?\')) document.getElementById(\'drop'.$id
                .'\').submit(); return false;" title="'._ONDELETE.'">'._ONDELETE.'</a>';
            $cont .= '<tr>'
                .'<td>'.title_tip(_MODUL.': '.htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8').'<br>'._DATE.': '
                .format_time((string)$time, _TIMESTRING)).$hword.'</td>'
                .'<td>'.$hmod.'</td>'
                .'<td>'.intval($hits).'</td>'
                .'<td>'.format_time((string)$time, _TIMESTRING).'</td>'
                .'<td>'.add_menu($edit.'||'.$drop).'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $pages = ceil($all / $anum);
        $cont .= setPageNumbers('pagenum', '', intval($all), intval($pages), $anum, 'name=search&amp;sort='.$sort
            .'&amp;order='.$order.$clink.'&amp;', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function top(): void {
    global $db, $afile, $conf;
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
    $cont = navi(1, 0, 0, 'search', 'top');
    $cont .= getSearchsum($where, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._SWORD.'</th><th>'._MODUL.'</th><th>'._HITS
            .'</th><th>'._DATE.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $word, $mod, $time, $hits] = $db->getSqlRow($result)) {
            $show = htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8');
            $mod = trim((string)$mod);
            $mlab = $mod ? getModuleName($mod) : _ALL;
            $mlab = $mlab ?: $mod;
            $hmod = filterTextHighlight(htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8'), $find);
            $hword = filterTextHighlight($show, $find);
            $link = getSearchlink($sort, $order, $num, $show, $fmod ?? '', 'top');
            $drop = '<form id="drop'.$id.'" action="'.$afile.'.php?name=search" method="post" style="display:none">'
                .'<input type="hidden" name="op" value="drop"><input type="hidden" name="id" value="'.$id.'">'
                .'<input type="hidden" name="sort" value="'.$sort.'"><input type="hidden" name="order" value="'.$order.'">'
                .'<input type="hidden" name="num" value="'.$num.'"><input type="hidden" name="find" value="'
                .htmlspecialchars($find ?? '', ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="fmod" value="'
                .htmlspecialchars($fmod ?? '', ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'
                .htmlspecialchars(getSearchtoken(), ENT_QUOTES, 'UTF-8').'"></form>';
            $edit = '<a href="'.$afile.'.php?'.$link.'&amp;op=edit&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>';
            $drop .= '<a href="#" OnClick="if (DelCheck(this, \''._DELETE.' &quot;'.$show.'&quot;?\')) document.getElementById(\'drop'.$id
                .'\').submit(); return false;" title="'._ONDELETE.'">'._ONDELETE.'</a>';
            $cont .= '<tr><td><a href="admin.php?'.getSearchlink(3, 2, 1, (string)$word, '', '').'">'.$hword.'</a></td><td>'
                .$hmod.'</td><td>'.intval($hits).'</td><td>'
                .format_time((string)$time, _TIMESTRING).'</td><td>'.add_menu($edit.'||'.$drop).'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $pages = ceil($all / $anum);
        $cont .= setPageNumbers('pagenum', '', intval($all), intval($pages), $anum, 'name=search&amp;op=top&amp;sort='
            .$sort.'&amp;order='.$order.$clink.'&amp;', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function conf(): void {
    global $afile, $conf;
    $allow = ['auto_links', 'faq', 'files', 'forum', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    $anum = intval($conf['search']['anum'] ?? 50);
    $anump = intval($conf['search']['anump'] ?? 10);
    $audit = getSearchaudit();
    $elist = getSearchenabled($audit);
    $rlist = getSearchready($audit);
    $ilist = getSearchinvalid($audit);
    setHead();
    $cont = navi(2, 0, 0, 'search', 'conf');
    if (getVar('get', 'auto', 'num', 0)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SEARCHAUTODONE.': '.intval(getVar('get', 'auto', 'num', 0))]);
    if (getVar('get', 'pick', 'num', 0)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SEARCHADDSEL.': '.intval(getVar('get', 'pick', 'num', 0))]);
    $cont .= checkPerms(CONFIG_DIR.'/search.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php?name=search" method="post"><input type="hidden" name="op" value="save">'
        .'<input type="hidden" name="token" value="'.htmlspecialchars(getSearchtoken(), ENT_QUOTES, 'UTF-8').'">'
        .'<table class="sl_table_conf">'
        .'<tr><td>'._SMODULE.':<div class="sl_small">'._CTRLINFO.'</div></td><td>'
        .modul('search', 'sl_conf', $conf['search']['mods'], 1, $allow).'</td></tr>'
        .'<tr><td>'._SEARCHLETMIN.':<div class="sl_small">'._SEARCHLETINFO.'</div></td><td><input type="number"'
        .' name="slet" value="'.$conf['search']['slet'].'" class="sl_conf" placeholder="'._SEARCHLETMIN.'" required></td></tr>'
        .'<tr><td>'._SEARCHNUM.':</td><td><input type="number" name="snum" value="'.$conf['search']['snum']
        .'" class="sl_conf" placeholder="'._SEARCHNUM.'" required></td></tr>'
        .'<tr><td>'._C_35.':</td><td><input type="number" name="snump" value="'.$conf['search']['snump']
        .'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
        .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$anum
        .'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
        .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$anump
        .'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
        .'<tr><td>'._SEARCHLIMIT.':<div class="sl_small">'._SEARCHLIMITINFO.'</div></td><td><input type="number"'
        .' name="slimit" value="'.$conf['search']['slimit'].'" class="sl_conf" placeholder="'._SEARCHLIMIT.'" required></td></tr>'
        .'<tr><td>'._ASEARCH.'</td><td>'.radio_form($conf['search']['asearch'], 'asearch').'</td></tr>'
        .'</table><table class="sl_table_conf"><tr><td class="sl_center"><input type="submit" value="'
        ._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= '<h3>'._SEARCHENABLED.'</h3>'.getSearchauditTable($elist, 'enabled');
    $cont .= '<h3>'._SEARCHREADY.'</h3><form action="'.$afile.'.php?name=search" method="post">'
        .'<input type="hidden" name="op" value="addmods"><input type="hidden" name="token" value="'.htmlspecialchars(getSearchtoken(), ENT_QUOTES, 'UTF-8').'">'
        .getSearchauditTable($rlist, 'ready').'<table class="sl_table_conf"><tr><td>'._SEARCHAUTO.':<div class="sl_small">'._SEARCHAUTOINFO.'</div></td></tr>'
        .'<tr><td class="sl_center"><input type="submit" value="'._SEARCHADDSEL.'" class="sl_but_blue"> <button type="submit" name="all" value="1" class="sl_but_blue">'._SEARCHADDALL.'</button></td></tr></table></form>';
    $cont .= '<h3>'._SEARCHINVALID.'</h3>'.getSearchauditTable($ilist, 'invalid');
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    if (!checkSearchtoken()) {
        setHead();
        echo navi(2, 0, 0, 'search', 'conf').setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => 'Security token mismatch']);
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
    setRedirect($afile.'.php?name=search&op=conf');
}

function auto(): void {
    global $afile, $conf;
    if (!checkSearchtoken()) {
        setHead();
        echo navi(2, 0, 0, 'search', 'conf').setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => 'Security token mismatch']);
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
    setRedirect($afile.'.php?name=search&op=conf&auto='.(count($mods) - $have));
}

function addmods(): void {
    global $afile, $conf;
    if (!checkSearchtoken()) {
        setHead();
        echo navi(2, 0, 0, 'search', 'conf').setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => 'Security token mismatch']);
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
    setRedirect($afile.'.php?name=search&op=conf&pick='.count($pick));
}

function edit(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    $sort = getVar('req', 'sort', 'num', 3);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    $find = trim((string)getVar('req', 'find', 'text', ''));
    $fmod = getVar('req', 'fmod', 'var', '');
    $result = $db->getSqlQuery('SELECT word, modul, time, score FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id]);
    setHead();
    $cont = navi(0, 0, 0, 'search', 'search');
    if ($db->getSqlRowCount($result) > 0) {
        [$word, $mod, $time, $score] = $db->getSqlRow($result);
        $hits = max(intval($score), 1);
        $cont .= setTemplateBasic('open');
        $cont .= '<form action="'.$afile.'.php?name=search" method="post"><table class="sl_table_form">'
            .'<tr><td>'._SWORD.':</td><td><input type="text" name="word" value="'
            .htmlspecialchars((string)$word, ENT_QUOTES, 'UTF-8').'" maxlength="255" class="sl_form" placeholder="'
            ._SWORD.'" required></td></tr>'
            .'<tr><td>'._MODUL.':</td><td><select name="modul" class="sl_form">'.getSearchmods((string)$mod).'</select></td></tr>'
            .'<tr><td>'._HITS.':</td><td><input type="number" name="hits" value="'.$hits.'" min="1" class="sl_form"'
            .' placeholder="'._HITS.'" required></td></tr>'
            .'<tr><td>'._DATE.':</td><td>'.datetime(1, 'time', (string)$time, 16, 'sl_form').'</td></tr>'
            .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="editsave"><input type="hidden"'
            .' name="id" value="'.$id.'"><input type="hidden" name="sort" value="'.$sort.'"><input type="hidden" name="order"'
            .' value="'.$order.'"><input type="hidden" name="num" value="'.$num.'"><input type="hidden" name="find" value="'
            .htmlspecialchars($find, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="fmod" value="'
            .htmlspecialchars($fmod, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'
            .htmlspecialchars(getSearchtoken(), ENT_QUOTES, 'UTF-8').'"><input type="submit" value="'
            ._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
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
    $find = trim((string)getVar('post', 'find', 'text', ''));
    $fmod = getVar('post', 'fmod', 'var', '');
    if (!checkSearchtoken()) {
        setHead();
        echo navi(0, 0, 0, 'search', 'search').setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => 'Security token mismatch']);
        setFoot();
        return;
    }
    $word = trim((string)getVar('post', 'word', 'text', ''));
    $mod = getVar('post', 'modul', 'var', '');
    $time = getVar('post', 'time', 'time');
    $hits = getVar('post', 'hits', 'num', 1);
    $hits = ($hits > 0) ? $hits : 1;
    if ($word === '') {
        setHead();
        echo navi(0, 0, 0, 'search', 'search').setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SWORD]);
        setFoot();
        return;
    }
    $db->getSqlQuery(
        'UPDATE '.PREFIX_DB.'_search SET word = :word, modul = :modul, time = :time, score = :score WHERE id = :id',
        ['word' => $word, 'modul' => $mod, 'time' => $time, 'score' => $hits, 'id' => $id]
    );
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod));
}

function del(): void {
    global $afile;
    setHead();
    $cont = navi(3, 0, 0, 'search', 'del');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SEARCHCLEARINFO]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php?name=search" method="post"><table class="sl_table_conf">'
        .'<tr><td>'._DELETE.':</td><td><select name="mode" class="sl_form"><option value="all">'._SEARCHCLEAR
        .'</option><option value="mod">'._SEARCHBYMOD.'</option><option value="days">'._SEARCHBYDAY
        .'</option><option value="empty">'._SEARCHEMPTY.'</option></select></td></tr>'
        .'<tr><td>'._MODUL.':</td><td><select name="cmod" class="sl_form">'.getSearchmods('').'</select></td></tr>'
        .'<tr><td>'._DAYS.':</td><td><input type="number" name="days" value="30" min="1" class="sl_form" placeholder="'._DAYS.'" required></td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="clear"><input type="hidden" name="token" value="'
        .htmlspecialchars(getSearchtoken(), ENT_QUOTES, 'UTF-8').'"><input type="submit" value="'._DELETE.'" class="sl_but_red"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function clear(): void {
    global $db, $afile;
    if (!checkSearchtoken()) {
        setHead();
        echo navi(3, 0, 0, 'search', 'del').setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => 'Security token mismatch']);
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
    setRedirect($afile.'.php?name=search&op=del');
}

function drop(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num', 0);
    $sort = getVar('post', 'sort', 'num', 3);
    $order = getVar('post', 'order', 'num', 2);
    $num = getVar('post', 'num', 'num', 1);
    $find = trim((string)getVar('post', 'find', 'text', ''));
    $fmod = getVar('post', 'fmod', 'var', '');
    if (checkSearchtoken() && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_search WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?'.getSearchlink($sort, $order, $num, $find, $fmod));
}

function info(): void {
    setHead();
    echo navi(4, 0, 0, 'search', 'info').'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: search(); break;
    case 'top': top(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'auto': auto(); break;
    case 'addmods': addmods(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'del': del(); break;
    case 'clear': clear(); break;
    case 'drop': drop(); break;
    case 'info': info(); break;
}
