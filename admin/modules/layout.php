<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The steps Ctrl+Z takes back, kept in the draft beside its layout
const LAYOUT_STEPS = 50;

# The sections kept in my sections of the library, a JSON file of LAYOUT_DIR beside the draft and shared by every editor of the builder
const LAYOUT_MINE = 'mine.json';

# A picture heavier than this, in bytes, is named by the checks before a publication: 500 KB
const LAYOUT_HEAVY = 512000;

# The terms in days a link to look at the draft may run, the middle one the default of its window
const LAYOUT_TERMS = [1, 7, 30];

# The groups of the library in their fixed order with the constants of their names; a type of another group falls into the last
const LAYOUT_GROUP = ['base' => '_LAYOUT_G_BASE', 'media' => '_LAYOUT_G_MEDIA', 'content' => '_LAYOUT_G_CONT', 'trust' => '_LAYOUT_G_TRUST',
    'contact' => '_LAYOUT_G_LINK', 'master' => '_LAYOUT_G_PRO'];

# The address of the screen with its parameters
function getLayoutUrl(array $pars = []): string {
    global $afile;
    return $afile.'.php?'.http_build_query(['name' => 'layout'] + $pars);
}

# The draft as the screen keeps it: the layout, the counter of links, the changed blocks and settings, the steps and the stamp of its last write; none yet starts as the site
function getLayoutDraft(): array {
    global $conf;
    $file = LAYOUT_DIR.'/'.Layout::DRAFT;
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $live = Layout::getLayoutData($conf['layout'] ?? []);
    if (!is_array($data) || !is_array($data['layout'] ?? null)) $data = ['layout' => $live];
    $dft = ['layout' => Layout::getLayoutData($data['layout']), 'link' => intval($data['link'] ?? 0), 'bnext' => intval($data['bnext'] ?? 0)];
    foreach (['blocks', 'bwas', 'conf', 'undo', 'redo'] as $key) $dft[$key] = is_array($data[$key] ?? null) ? $data[$key] : [];
    foreach (['rev', 'ed', 'by'] as $key) $dft[$key] = is_string($data[$key] ?? null) ? $data[$key] : '';
    $dft['at'] = intval($data['at'] ?? 0);
    $dft['layout']['next'] = max($dft['layout']['next'], $live['next']);
    $mod = getVar('req', 'mod', 'var', '');
    if ($mod !== '') setLayoutSlotPage($dft, $mod);
    return $dft;
}

# The page of a module the edit of a page in the floating menu opened while no page holds its slot: made in memory, written by the first change
function setLayoutSlotPage(array &$dft, string $mod): void {
    global $conf;
    if (!isset(getLayoutModules()[$mod])) return;
    foreach ($dft['layout']['pages'] as $page) {
        foreach ($page['sections'] as $sec) if (($sec['type'] ?? '') === Layout::SLOT && (string)($sec['module'] ?? '') === $mod) return;
    }
    $cols = isset($conf['node']['types'][$mod]) ? (getNodeTypeMap()[$mod]->settings['view'] ?? []) : ($conf['modules'][$mod] ?? []);
    [$side, $top] = array_map(static fn(mixed $v): string => in_array((string)$v, ['0', '1', '2', '3'], true) ? (string)$v : '0', [$cols['side'] ?? '', $cols['top'] ?? '']);
    $dft['base'] = $dft['layout'];
    $key = $dft['layout']['next']++;
    $sec = ['id' => $dft['layout']['next']++, 'type' => Layout::SLOT, 'on' => true, 'module' => $mod, 'who' => '0', 'lang' => '', 'from' => '', 'till' => '', 'anchor' => '',
        'phone' => true, 'desk' => true];
    $dft['layout']['pages'][$key] = ['title' => getModuleName($mod), 'addr' => '', 'desc' => '', 'band' => true, 'side' => $side, 'top' => $top, 'og' => '', 'noindex' => false,
        'who' => '0', 'sections' => [$sec]];
    $dft['made'] = ['key' => $key, 'mod' => $mod];
}

# The draft as its file holds it, without the page of a module the screen made in memory: what Ctrl+Z, the state, the checks and the publication read
function getLayoutStored(array $dft): array {
    if (isset($dft['base'])) $dft['layout'] = $dft['base'];
    unset($dft['base'], $dft['made']);
    return $dft;
}

# Write one JSON file of LAYOUT_DIR whole through a file beside it and a rename, so no reader ever sees half of it; the folder comes with the first write
function setLayoutFile(string $name, array $data): bool {
    if (!is_dir(LAYOUT_DIR) && !mkdir(LAYOUT_DIR, 0755, true) && !is_dir(LAYOUT_DIR)) return false;
    $file = LAYOUT_DIR.'/'.$name;
    $temp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($temp, $json, LOCK_EX) === false) return false;
    if (rename($temp, $file)) return true;
    unlink($temp);
    return false;
}

# Write the draft with a new stamp of its revision and time, which the screen of every editor posts back to find a write of another one
function setLayoutDraft(array &$dft): bool {
    unset($dft['base'], $dft['made']);
    [$dft['rev'], $dft['at']] = [bin2hex(random_bytes(8)), time()];
    return setLayoutFile(Layout::DRAFT, $dft);
}

# Whether the draft is the one this editor saw last: a write of another editor since then refuses with its name and time, a step of this editor never does
function checkLayoutEditor(array &$dft): string {
    global $admin;
    $rev = getVar('post', 'rev', 'var', '');
    $ed = getVar('post', 'ed', 'var', '');
    if ($dft['rev'] !== '' && $rev !== $dft['rev'] && $ed !== $dft['ed']) return sprintf(_LAYOUT_DRAFTBY, ($dft['by'] !== '') ? $dft['by'] : '—', date(_TIMESTRING, $dft['at']));
    [$dft['by'], $dft['ed']] = [substr((string)($admin[1] ?? ''), 0, 25), $ed];
    return '';
}

# What one step of Ctrl+Z puts back: the layout, the blocks and the settings of the draft
function getLayoutState(array $dft): array {
    return ['layout' => $dft['layout'], 'blocks' => $dft['blocks'], 'conf' => $dft['conf']];
}

# Whether the draft differs from the site; the counter of ids is no content, so a draft brought back keeps its larger counter and stays clean
function isLayoutDirty(array $dft): bool {
    global $conf;
    $live = Layout::getLayoutData($conf['layout'] ?? []);
    return $dft['blocks'] !== [] || $dft['conf'] !== [] || array_replace($live, ['next' => 0]) != array_replace($dft['layout'], ['next' => 0]);
}

# The layout read as a moderator sees it, in the theme of the site
function getLayoutView(array $lay): Layout {
    global $conf;
    return new Layout($lay, (string)($conf['theme'] ?? ''), true, true);
}

# The pick of the screen: page:<n>, sec:<n>, part:<key>, menu:<key>, menu:<key>:<n> or blk:<id>; anything else is no pick
function getLayoutPick(): string {
    $sel = getVar('req', 'sel', 'raw', '');
    $mask = '/^(?:page:[1-9][0-9]{0,9}|sec:[1-9][0-9]{0,9}|part:[a-z][a-z0-9]{0,31}|menu:[a-z][a-z0-9]{0,31}(?::[1-9][0-9]{0,9})?|blk:n?[1-9][0-9]{0,9})$/D';
    return (is_string($sel) && preg_match($mask, $sel) === 1) ? $sel : '';
}

# The page the screen shows: the one asked for while the draft has it, else the home page, else the first
function getLayoutPageKey(array $lay): int {
    $key = getVar('req', 'page', 'num', 0);
    if (isset($lay['pages'][$key])) return $key;
    if (isset($lay['pages'][$lay['home']])) return $lay['home'];
    return intval(array_key_first($lay['pages']) ?? 0);
}

# The tab of the tree: the page, the menus or the parts of the theme
function getLayoutTab(string $sel): string {
    $tab = getVar('req', 'tab', 'var', '');
    if (in_array($tab, ['page', 'menu', 'theme'], true)) return $tab;
    return str_starts_with($sel, 'menu:') ? 'menu' : 'page';
}

# The page and the place in it of a section by its id, or null
function getLayoutSecPlace(array $lay, int $sid): ?array {
    foreach ($lay['pages'] as $key => $page) {
        foreach ($page['sections'] as $i => $sec) if (intval($sec['id'] ?? 0) === $sid) return [$key, $i];
    }
    return null;
}

# The place of a menu item by its id: its index, and the index among the kids for an item one level down
function getLayoutItemPlace(array $list, int $iid): ?array {
    foreach ($list as $i => $item) {
        if (intval($item['id'] ?? 0) === $iid) return [$i];
        foreach (is_array($item['kids'] ?? null) ? $item['kids'] : [] as $j => $kid) if (intval($kid['id'] ?? 0) === $iid) return [$i, $j];
    }
    return null;
}

# The title a page goes by in the screen: its own, else the word of the home page or of the page 404
function getLayoutPageTitle(array $lay, int $key): string {
    $title = trim((string)($lay['pages'][$key]['title'] ?? ''));
    if ($title !== '') return $title;
    if ($key === $lay['home']) return _HOME;
    return ($key === $lay['notfound']) ? _LAYOUT_PAGE404 : _LAYOUT_NEWPAGE;
}

# The words of a section: the title it carries, cut for a list unless whole, the module of its slot, or nothing
function getLayoutSecName(array $sec, bool $whole = false): string {
    if (($sec['type'] ?? '') === Layout::SLOT) {
        $mod = (string)($sec['module'] ?? '');
        return ($mod === '') ? _HOME : getModuleName($mod);
    }
    $title = trim((string)($sec['title'] ?? ''));
    return (!$whole && mb_strlen($title) > 40) ? mb_substr($title, 0, 39).'…' : $title;
}

# The name of a section in a list: its type, then its own words in quotes unless they only repeat the type
function getLayoutSecLabel(array $sec, array $types): string {
    $type = (string)($sec['type'] ?? '');
    $title = (string)($types[$type]['title'] ?? $type);
    $word = getLayoutSecName($sec);
    return ($word !== '' && $word !== $title) ? $title.' «'.$word.'»' : $title;
}

# The section types the library offers, the slot the core owns first of its group, sorted by LAYOUT_GROUP and the key order of a type, one without it after the ordered
function getLayoutTypes(Layout $view): array {
    $out = [Layout::SLOT => ['title' => _LAYOUT_SLOT, 'note' => _LAYOUT_SLOT_INFO, 'icon' => 'puzzle', 'group' => 'content', 'order' => 0, 'frag' => true, 'one' => true,
        'desc' => ['fields' => [['key' => 'module', 'label' => '_MODUL', 'type' => 'choice', 'options' => getLayoutModules()]], 'make' => ['module' => '']]]];
    foreach ($view->getSectionTypes() as $type => $one) {
        if ($one['root'] !== 'theme') {
            getLang($one['root']);
            getLang($one['root'], true);
        }
        $desc = $one['desc'];
        $group = (string)($desc['group'] ?? '');
        $out[$type] = ['title' => getConst((string)($desc['title'] ?? $type)), 'note' => getConst((string)($desc['note'] ?? '')), 'icon' => (string)($desc['icon'] ?? 'square'),
            'group' => isset(LAYOUT_GROUP[$group]) ? $group : 'master', 'order' => is_int($desc['order'] ?? null) ? $desc['order'] : PHP_INT_MAX, 'desc' => $desc,
            'frag' => $one['frag'] !== '', 'one' => false];
    }
    $rank = array_flip(array_keys(LAYOUT_GROUP));
    uasort($out, static fn(array $a, array $b): int => [$rank[$a['group']], $a['order']] <=> [$rank[$b['group']], $b['order']]);
    return $out;
}

# The modules a slot may hold: the home module of the settings first, then every active site module and Node type by its name
function getLayoutModules(): array {
    global $conf;
    $out = ['' => '_HOME'];
    foreach ($conf['modules'] as $mod => $info) if (intval($info['type'] ?? 1) === 1 && $mod !== 'node' && !empty($info['active'])) $out[$mod] = getModuleName($mod);
    foreach (array_keys($conf['node']['types'] ?? []) as $mod) $out[$mod] = getModuleName($mod);
    return $out;
}

# The fields of the tab of display a section shares with a block of the site: audience, language, dates, anchor and devices
function getLayoutShowFields(): array {
    global $conf;
    $out = [['key' => 'who', 'label' => '_LAYOUT_WHO', 'type' => 'choice', 'options' => ['0' => '_MVALL', '1' => '_MVUSERS', '2' => '_MVADMIN', '3' => '_MVANON']]];
    if (!empty($conf['multilingual'])) $out[] = ['key' => 'lang', 'label' => '_LANGUAGE', 'type' => 'choice', 'options' => ['' => '_ALL'] + getLanguageList()];
    $out[] = ['key' => 'from', 'label' => '_LAYOUT_FROMDAY', 'type' => 'date'];
    $out[] = ['key' => 'till', 'label' => '_LAYOUT_TILLDAY', 'type' => 'date'];
    $out[] = ['key' => 'anchor', 'label' => '_LAYOUT_ANCHOR', 'type' => 'anchor'];
    $out[] = ['key' => 'phone', 'label' => '_LAYOUT_ONPHONE', 'type' => 'bool', 'show' => 'switch'];
    $out[] = ['key' => 'desk', 'label' => '_LAYOUT_ONDESK', 'type' => 'bool', 'show' => 'switch'];
    return $out;
}

# The fields of a page; a page holding a slot answers at its module's address with its module's words, the page 404 has no address
function getLayoutPageFields(array $lay, int $key): array {
    $slot = getLayoutView($lay)->getSlotName($key) !== null;
    $own = !$slot && $key !== $lay['notfound'] && $key !== $lay['home'];
    $out = [['key' => 'title', 'label' => '_TITLE', 'type' => 'text', 'max' => 255]];
    if ($own) {
        $out[] = ['key' => 'addr', 'label' => '_LAYOUT_URL', 'type' => 'slug'];
        $out[] = ['key' => 'desc', 'label' => '_LAYOUT_DESC', 'type' => 'textarea', 'max' => 500];
        $out[] = ['key' => 'og', 'label' => '_LAYOUT_OGIMG', 'type' => 'file'];
        $out[] = ['key' => 'noindex', 'label' => '_LAYOUT_INDEX', 'type' => 'choice', 'show' => 'seg', 'options' => ['0' => '_LAYOUT_SHOWN', '1' => '_LAYOUT_HIDDEN']];
        $out[] = ['key' => 'who', 'label' => '_LAYOUT_WHO', 'type' => 'choice', 'options' => ['0' => '_MVALL', '1' => '_MVUSERS']];
    }
    $out[] = ['key' => 'band', 'label' => '_LAYOUT_BAND', 'type' => 'bool', 'show' => 'switch'];
    $out[] = ['key' => 'side', 'label' => '_BLOCKS_SIDE', 'type' => 'choice',
        'options' => ['0' => '_BLOCKS_MOD0', '1' => '_BLOCKS_MOD1', '2' => '_BLOCKS_MOD2', '3' => '_BLOCKS_MOD3']];
    $out[] = ['key' => 'top', 'label' => '_BLOCKS_TOP', 'type' => 'choice',
        'options' => ['0' => '_BLOCKS_MODC0', '1' => '_BLOCKS_MODC1', '2' => '_BLOCKS_MODC2', '3' => '_BLOCKS_MODC3']];
    return $out;
}

# The fields of a menu item; the icon only for a menu whose theme draws icons
function getLayoutItemFields(array $menu): array {
    $out = [['key' => 'title', 'label' => '_TITLE', 'type' => 'text', 'max' => 255], ['key' => 'href', 'label' => '_LAYOUT_HREF', 'type' => 'url']];
    $out[] = ['key' => 'who', 'label' => '_LAYOUT_WHO', 'type' => 'choice', 'options' => ['0' => '_MVALL', '1' => '_MVUSERS', '2' => '_MVADMIN', '3' => '_MVANON']];
    if (!empty($menu['icons'])) $out[] = ['key' => 'icon', 'label' => '_ICON', 'type' => 'icon'];
    $out[] = ['key' => 'blank', 'label' => '_LAYOUT_BLANK', 'type' => 'bool', 'show' => 'switch'];
    return $out;
}

# The fields of a block in the order of its own screen; the field of its kind shows for that kind only, a block of the system keeps its kind
function getLayoutBlockFields(array $blk, array $zones): array {
    global $conf;
    $out = [['key' => 'title', 'label' => '_TITLE', 'type' => 'text', 'max' => 60]];
    if ($blk['kind'] !== 'sys') {
        $out[] = ['key' => 'kind', 'label' => '_LAYOUT_BKIND', 'type' => 'choice', 'show' => 'seg', 'options' => ['file' => '_FILE', 'html' => 'HTML', 'rss' => 'RSS']];
        $files = [];
        foreach (scandir(BASE_DIR.'/blocks') ?: [] as $file) if (preg_match('/^[a-z0-9_\-]{1,251}\.php$/i', $file)) $files[$file] = $file;
        $out[] = ['key' => 'bfile', 'label' => '_FILENAME', 'type' => 'choice', 'options' => $files, 'when' => ['kind', 'file']];
        $out[] = ['key' => 'content', 'label' => '_CONTENT', 'type' => 'textarea', 'when' => ['kind', 'html']];
        $out[] = ['key' => 'url', 'label' => '_RSSFILE', 'type' => 'url', 'max' => 200, 'when' => ['kind', 'rss']];
    }
    $pos = [];
    foreach ($zones as $zone => $one) $pos[$zone] = (string)($one['title'] ?? $zone);
    $out[] = ['key' => 'bpos', 'label' => '_POSITION', 'type' => 'choice', 'options' => $pos];
    $out[] = ['key' => 'which', 'label' => '_BLOCK_VIEW', 'type' => 'checks'];
    $out[] = ['key' => 'status', 'label' => '_ACTIVATE2', 'type' => 'bool', 'show' => 'switch'];
    $out[] = ['key' => 'days', 'label' => '_EXPIRATION', 'type' => 'int', 'min' => 0, 'max' => 3650];
    $out[] = ['key' => 'action', 'label' => '_AFTEREXPIRATION', 'type' => 'choice', 'options' => ['d' => '_DEACTIVATE', 'r' => '_DELETE']];
    if (!empty($conf['multilingual'])) $out[] = ['key' => 'lang', 'label' => '_LANGUAGE', 'type' => 'choice', 'options' => ['' => '_ALL'] + getLanguageList()];
    $out[] = ['key' => 'view', 'label' => '_VIEWPRIV', 'type' => 'choice', 'options' => ['0' => '_MVALL', '1' => '_MVUSERS', '2' => '_MVADMIN', '3' => '_MVANON']];
    return $out;
}

# The marks and places a block may name in its where: the pages of the site by name, then the marks of the panel, a value no page answers kept until it is dropped
function getLayoutWhere(array $where): array {
    $places = getBlockPlaces();
    foreach (getBlockStray($where, $places) as $val) $places[$val] = [_BLOCK_NOWHERE, $val];
    $out = [];
    foreach ($places as $key => [$kind, $label]) $out[$key] = $label.' · '.$kind;
    return $out + ['ihome' => _HOME, 'home' => _INHOME, 'all' => _BLOCK_ALL, 'otricanie' => _DENYING, 'infly' => _INFLY, 'flyfix' => _FLY_FIX];
}

# The blocks as the draft sees them, by id in the order of their zones: the rows of _blocks with the draft laid over, the deleted ones out, the new ones in
function getLayoutBlocks(array $dft): array {
    global $db;
    $out = [];
    $cols = 'id, bkey, title, content, url, bpos, weight, status, lang, bfile, view, expire, action, which';
    $res = $db->getSqlQuery('SELECT '.$cols.' FROM '.PREFIX_DB.'_blocks ORDER BY bpos, weight, id');
    foreach (($res ? $db->getSqlRows($res) : []) ?: [] as $row) {
        $left = $row['expire'] ? strtotime((string)$row['expire']) - time() : 0;
        $kind = ($row['bkey'] !== '') ? 'sys' : (($row['bfile'] !== '') ? 'file' : (($row['url'] !== '') ? 'rss' : 'html'));
        $out[(string)$row['id']] = [
            'id' => (string)$row['id'], 'key' => (string)$row['bkey'], 'title' => (string)$row['title'], 'kind' => $kind, 'bfile' => (string)$row['bfile'],
            'content' => (string)$row['content'], 'url' => (string)$row['url'], 'bpos' => (string)$row['bpos'], 'weight' => intval($row['weight']),
            'status' => !empty($row['status']), 'view' => (string)$row['view'], 'lang' => (string)$row['lang'], 'days' => ($left > 0) ? intval(ceil($left / 86400)) : 0,
            'action' => in_array($row['action'], ['d', 'r'], true) ? (string)$row['action'] : 'd', 'expire' => (string)$row['expire'],
            'which' => array_values(array_filter(explode(',', (string)$row['which']), 'strlen')),
            'new' => false,
        ];
    }
    foreach ($dft['blocks'] as $bid => $set) {
        $bid = (string)$bid;
        if (!empty($set['del'])) {
            unset($out[$bid]);
            continue;
        }
        if (isset($out[$bid])) $out[$bid] = array_replace($out[$bid], array_intersect_key($set, $out[$bid]));
        elseif (!empty($set['new'])) $out[$bid] = $set;
    }
    uasort($out, static fn(array $one, array $two): int => [$one['bpos'], $one['weight'], $one['id']] <=> [$two['bpos'], $two['weight'], $two['id']]);
    return $out;
}

# Lay the changed fields of a block over the draft: a field equal to its row again leaves it, the places compared as a set; the row first touched is what publishing checks
function setLayoutBlock(array &$dft, array $blk, array $set): void {
    $bid = $blk['id'];
    if ($blk['new']) {
        $dft['blocks'][$bid] = array_replace($blk, $set);
        return;
    }
    if (!isset($dft['bwas'][$bid]) || !isset($dft['blocks'][$bid])) $dft['bwas'][$bid] = array_diff_key($blk, ['new' => true]);
    $base = $dft['bwas'][$bid];
    $now = array_replace(is_array($dft['blocks'][$bid] ?? null) ? $dft['blocks'][$bid] : [], $set);
    foreach ($now as $key => $val) {
        $was = $base[$key] ?? null;
        $same = (is_array($val) && is_array($was)) ? !array_diff($val, $was) && !array_diff($was, $val) : $was === $val;
        if (array_key_exists($key, $base) && $same) unset($now[$key]);
    }
    if ($now === []) unset($dft['blocks'][$bid]);
    else $dft['blocks'][$bid] = $now;
}

# Number one zone of blocks in the given order from one, each changed weight laid over the draft
function setLayoutZone(array &$dft, array $blks, string $zone, array $ids): void {
    $num = 0;
    foreach ($ids as $bid) {
        if (!isset($blks[$bid])) continue;
        $num++;
        if ($blks[$bid]['bpos'] !== $zone || $blks[$bid]['weight'] !== $num) setLayoutBlock($dft, $blks[$bid], ['bpos' => $zone, 'weight' => $num]);
    }
}

# Check one value of a field: a type of Field by Field itself, a kind of the builder here; the answer is the error code or empty, and the canonical value
function getLayoutValue(array $one, mixed $raw): array {
    $key = (string)$one['key'];
    $type = (string)$one['type'];
    $text = is_string($raw) ? trim($raw) : '';
    if (in_array($type, ['text', 'textarea', 'select', 'bool', 'int', 'date', 'email', 'url'], true)) {
        $rule = Layout::getFieldRule($key, $type, $one);
        $fld = new Field();
        $err = $fld->checkFieldValues([$key => $rule], [$key => $raw], false)[$key] ?? '';
        if ($err !== '') return [$err, null];
        $val = $fld->filterFieldValues([$key => $rule], [$key => $raw])[$key] ?? null;
        if ($type === 'bool') return ['', (bool)$val];
        if ($type === 'int') return ['', intval($val)];
        return ['', ($val === null) ? '' : $val];
    }
    $good = match ($type) {
        'markdown', 'code' => is_string($raw) && mb_check_encoding($raw, 'UTF-8') && strlen($raw) <= 65535,
        'file' => $text === '' || (preg_match('#^[a-z0-9][a-z0-9_-]*(?:/[A-Za-z0-9][A-Za-z0-9_.-]*)+$#D', $text) === 1 && !str_contains($text, '..')),
        'focus' => in_array($text, Layout::FOCUS, true),
        'icon' => $text === '' || preg_match(Layout::ICON, $text) === 1,
        'tone' => $text === '' || in_array($text, Layout::TONES, true),
        'anchor' => $text === '' || preg_match(Layout::ANCHOR, $text) === 1,
        'slug' => $text === '' || filterSlug($text) !== '',
        'choice' => is_scalar($raw) && in_array((string)$raw, array_map('strval', array_keys(is_array($one['options'] ?? null) ? $one['options'] : [])), true),
        'video', 'page', 'point' => is_string($raw) && mb_check_encoding($raw, 'UTF-8') && mb_strlen($text) <= 2048 && !preg_match('/[\x00-\x1F\x7F]/', $text),
        'nodetype' => is_array($raw) && array_filter($raw, static fn($v): bool => !is_string($v) || ($v !== '' && preg_match('/^[a-z][a-z0-9]{0,19}$/D', $v) !== 1)) === [],
        'noderef', 'blockref' => (is_int($raw) && $raw >= 0) || (is_string($raw) && preg_match('/^[0-9]{0,10}$/D', $text) === 1),
        default => false,
    };
    if (!$good) return ['format', null];
    if ($type === 'markdown' || $type === 'code') return ['', str_replace(["\r\n", "\r"], "\n", $raw)];
    if ($type === 'choice') return ['', (string)$raw];
    if ($type === 'nodetype') return ['', array_values(array_unique(array_filter($raw, static fn(string $v): bool => $v !== '')))];
    if ($type === 'noderef' || $type === 'blockref') return ['', intval($raw)];
    return ['', ($type === 'slug') ? filterSlug($text) : $text];
}

# Take the posted values of a list of fields into an array of values; a field missing from the post keeps its value, a refused one is named, a file with its reason
function setLayoutValues(array $list, array $post, array &$vals, array &$bad, string $base = 'field'): void {
    foreach ($list as $one) {
        $key = (string)($one['key'] ?? '');
        if ($key === '' || !array_key_exists($key, $post)) continue;
        if ($one['type'] === 'checks') continue;
        if ($one['type'] === 'file') {
            [$err, $val] = getLayoutFile(getLayoutFieldId($base, $key).'-up', $post[$key], is_string($vals[$key] ?? null) ? $vals[$key] : '');
            $err = ($err !== '') ? getConst((string)$one['label']).' — '.$err : '';
        } else {
            [$err, $val] = getLayoutValue($one, $post[$key]);
            $err = ($err !== '') ? getConst((string)$one['label']) : '';
        }
        if ($err !== '') {
            $bad[] = $err;
            continue;
        }
        $vals[$key] = $val;
    }
}

# Whether a value of the draft is the one of the site: a list of words as a set, a list of items in its order, a switch as its state, any other value as the posted text
function isLayoutSame(mixed $one, mixed $two): bool {
    $flat = static fn(mixed $v): bool => is_array($v) && array_is_list($v) && array_filter($v, 'is_array') === [];
    if ($flat($one) && $flat($two)) {
        sort($one);
        sort($two);
        return $one == $two;
    }
    if (is_bool($one) || is_bool($two)) return (bool)$one === (bool)$two;
    if (($one === null || is_scalar($one)) && ($two === null || is_scalar($two))) return (string)$one === (string)$two;
    return $one == $two;
}

# The id of the control of one field in the inspector; the file a file field sends with the form goes under this id with "-up", which save() reads back
function getLayoutFieldId(string $base, string $key): string {
    return 'f-lay-'.trim((string)preg_replace('/[^a-z0-9]+/', '-', $base.'-'.$key), '-');
}

# The addresses an address field offers: the home page and the pages of the draft with an address of their own, then every module and Node type a slot may hold by name
function getLayoutAddrs(array $lay): array {
    $pages = [];
    $view = getLayoutView($lay);
    foreach ($lay['pages'] as $key => $page) {
        $addr = (string)($page['addr'] ?? '');
        if ($key === $lay['home']) $pages = ['/' => getLayoutPageTitle($lay, $key)] + $pages;
        elseif ($key !== $lay['notfound'] && $addr !== '' && $view->getSlotName($key) === null) $pages['/'.$addr] = getLayoutPageTitle($lay, $key);
    }
    $mods = [];
    foreach (getLayoutModules() as $mod => $word) if ($mod !== '') $mods['/'.$mod] = getConst((string)$word);
    asort($mods, SORT_NATURAL | SORT_FLAG_CASE);
    return [_LAYOUT_PAGES => $pages, _MODULES => $mods];
}

# The Node types a feed offers, active and given to blocks in their settings, as the engine of node.php reads them; each by its name and its title
function getLayoutFeedTypes(): array {
    $out = [];
    foreach (getNodeTypeMap() as $type) if ($type->active && $type->settings['integrations']['blocks']) $out[$type->name] = getModuleName($type->name);
    return $out;
}

# The records a visitor reads, published within their dates as NodeQuery reads them for a guest: the one held, then up to twenty by title or number, the newest first
function getLayoutNodes(int $now, string $find): array {
    global $db;
    $sql = 'SELECT n.id, n.title, t.name FROM '.PREFIX_DB.'_nodes AS n INNER JOIN '.PREFIX_DB.'_node_types AS t ON t.id = n.tid WHERE n.status = :st'
        .' AND n.published <= NOW() AND (n.expires IS NULL OR n.expires > NOW()) AND ';
    $pars = ['st' => NodeStatus::Published->value];
    $res = ($now > 0) ? $db->getSqlQuery($sql.'n.id = :now', $pars + ['now' => $now]) : false;
    $rows = $res ? ($db->getSqlRows($res) ?: []) : [];
    if ($find !== '') {
        $like = '%'.strtr($find, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $res = $db->getSqlQuery($sql.'(n.title LIKE :find ESCAPE \'!\' OR n.id = :num) ORDER BY n.id DESC LIMIT 20', $pars + ['find' => $like, 'num' => intval($find)]);
        $rows = array_merge($rows, $res ? ($db->getSqlRows($res) ?: []) : []);
    }
    $out = [];
    foreach ($rows as $row) $out[intval($row['id'])] = ['title' => (string)$row['title'], 'type' => (string)$row['name']];
    return $out;
}

# The options of the choice of a record: none, the one held - named gone when it no longer answers, so a save of another field keeps it - then the found ones by type
function getLayoutNodeHtml(int $now, string $find): string {
    global $tpl;
    $rows = getLayoutNodes($now, $find);
    $html = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _LAYOUT_PICKNODE, 'is_selected' => $now === 0]);
    if ($now > 0 && !isset($rows[$now])) {
        $html .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$now, 'label_text' => sprintf(_LAYOUT_NODEGONE, $now), 'is_selected' => true]);
    }
    $groups = [];
    foreach ($rows as $id => $row) {
        $one = $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$id, 'label_text' => sprintf(_LAYOUT_NUMBERED, $id, $row['title']), 'is_selected' => $id === $now]);
        if ($id === $now) $html .= $one;
        else $groups[$row['type']] = ($groups[$row['type']] ?? '').$one;
    }
    foreach ($groups as $type => $part) $html .= $tpl->getHtmlFrag('select-optgroup', ['label_text' => getModuleName($type), 'options_html' => $part]);
    return $html;
}

# The values of one part of a layout file by a list of fields, each checked as the inspector checks it; a refused value is named with its place
function getLayoutClean(array $list, array $vals, string $where, array &$bad): array {
    $out = [];
    foreach ($list as $one) {
        $key = (string)($one['key'] ?? '');
        if ($key === '' || !array_key_exists($key, $vals)) continue;
        [$err, $val] = getLayoutValue($one, $vals[$key]);
        if ($err !== '') $bad[] = $where.' · '.getConst((string)$one['label']);
        else $out[$key] = $val;
    }
    return $out;
}

# The items of a list in a layout file by the description of the list, no more than its max
function getLayoutCleanItems(array $desc, mixed $list, string $where, array &$bad): array {
    $out = [];
    $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
    foreach (is_array($list) ? array_values($list) : [] as $item) {
        if (count($out) >= max(0, intval($desc['max'] ?? 0))) break;
        if (is_array($item)) $out[] = getLayoutClean($fields, $item, $where, $bad);
    }
    return $out;
}

# A layout file read by the descriptions of the theme and its modules, a value none of them describes left out; null with the reasons when a value or an id is refused
function getLayoutImport(array $data, array $now, array $types, array $places): array {
    $lay = Layout::getLayoutData($data);
    if ((string)($data['v'] ?? '') !== (string)Layout::VERSION || !isset($lay['pages'][$lay['home']], $lay['pages'][$lay['notfound']])) return [null, []];
    [$bad, $ids] = [[], []];
    $out = ['v' => Layout::VERSION, 'shipped' => false, 'next' => 1, 'home' => $lay['home'], 'notfound' => $lay['notfound'], 'theme' => [], 'menus' => [], 'pages' => []];
    $take = static function (mixed $id) use (&$ids): bool {
        if (!is_int($id) || $id < 1 || isset($ids[$id])) return false;
        return $ids[$id] = true;
    };
    foreach ($places['parts'] as $pkey => $part) {
        if (!is_array($part)) continue;
        $where = getConst((string)($part['title'] ?? $pkey));
        $out['theme'] += getLayoutClean(is_array($part['fields'] ?? null) ? $part['fields'] : [], $lay['theme'], $where, $bad);
        $list = is_array($part['items'] ?? null) ? $part['items'] : [];
        $lkey = (string)($list['key'] ?? '');
        if (preg_match(Layout::FIELD, $lkey) === 1) $out['theme'][$lkey] = getLayoutCleanItems($list, $lay['theme'][$lkey] ?? [], $where, $bad);
    }
    foreach ($places['menus'] as $mkey => $menu) {
        if (!is_array($menu)) continue;
        $where = getConst((string)($menu['title'] ?? $mkey));
        $walk = static function (mixed $list, int $lev) use (&$walk, &$bad, $take, $menu, $where): array {
            $out = [];
            foreach (is_array($list) ? $list : [] as $item) {
                if (!is_array($item)) continue;
                if (!$take($item['id'] ?? null)) $bad[] = $where.' · id';
                $kids = ($lev > 1) ? $walk($item['kids'] ?? [], $lev - 1) : [];
                $out[] = ['id' => intval($item['id'] ?? 0)] + getLayoutClean(getLayoutItemFields($menu), $item, $where, $bad) + ['kids' => $kids];
            }
            return $out;
        };
        $out['menus'][$mkey] = $walk($lay['menus'][$mkey] ?? [], max(1, intval($menu['depth'] ?? 1)));
    }
    foreach ($lay['pages'] as $pkey => $page) {
        $where = getLayoutPageTitle($lay, $pkey);
        if (!$take($pkey)) $bad[] = $where.' · id';
        $vals = array_replace($page, ['noindex' => empty($page['noindex']) ? '0' : '1']);
        $clean = getLayoutClean(getLayoutPageFields($lay, $pkey), $vals, $where, $bad);
        if (isset($clean['noindex'])) $clean['noindex'] = $clean['noindex'] === '1';
        $secs = [];
        foreach ($page['sections'] as $sec) {
            if (!is_array($sec)) continue;
            $type = (string)($sec['type'] ?? '');
            $one = $types[$type] ?? null;
            if ($one === null) {
                $bad[] = sprintf(_LAYOUT_IMPTYPE, $where, $type);
                continue;
            }
            $desc = $one['desc'];
            $place = $where.' · '.$one['title'];
            if (!$take($sec['id'] ?? null)) $bad[] = $place.' · id';
            $fields = array_merge(is_array($desc['fields'] ?? null) ? $desc['fields'] : [], getLayoutShowFields());
            $item = ['id' => intval($sec['id'] ?? 0), 'type' => $type, 'on' => !empty($sec['on'])] + getLayoutClean($fields, $sec, $place, $bad);
            if (is_array($desc['items'] ?? null)) $item['items'] = getLayoutCleanItems($desc['items'], $sec['items'] ?? [], $place, $bad);
            $secs[] = $item;
        }
        $out['pages'][$pkey] = $clean + ['sections' => $secs];
    }
    $out['next'] = max($lay['next'], intval($now['next'] ?? 0), max(array_merge([0], array_keys($ids))) + 1);
    return $bad ? [null, array_values(array_unique($bad))] : [$out, []];
}

# A new file of a file field goes into the uploads of the builder, a pick of its file window is a path there, the value it had stays; the answer is the refusal and the path
function getLayoutFile(string $up, mixed $raw, string $now): array {
    $file = $_FILES[$up] ?? null;
    $rule = getUploadPlaceRule('layout.attach');
    $text = is_string($raw) ? trim($raw) : '';
    if (is_array($file) && intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (!$rule['ok'] || !checkEditorUploadAccess('layout', $rule)) return [_ACCESSDENIED, null];
        $res = getUploadService()->addUploadedFile($file, $rule, (string)$rule['store'], (string)$rule['mod'], getEditorFileOwner('layout'));
        return $res['ok'] ? ['', (string)$res['path']] : [getUploadFailText((string)$res['error'], $rule), null];
    }
    if ($text === '' || $text === $now) return ['', $text];
    $got = $rule['ok'] ? getUploadTakenFile($rule, $text) : ['ok' => false];
    return $got['ok'] ? ['', $rule['store'].'/'.$got['file']['path']] : [_ERROR_FILE, null];
}

# The rows of the inspector for a list of fields: a type of Field drawn by Field, a kind of the builder by the canon; a field the site has carries its way back, shown while unlike
function getLayoutRows(array $list, array $vals, string $base, string $tab = '', ?array $live = null, array $addrs = [], array $blks = []): array {
    global $tpl;
    $rows = [];
    $fld = new Field();
    $pre = (preg_match('/^item\[([0-9]{1,3})\]$/D', $base, $hit) === 1) ? $hit[1].'-' : '';
    foreach ($list as $one) {
        $key = (string)($one['key'] ?? '');
        if ($key === '' || (string)($one['tab'] ?? '') !== $tab) continue;
        $type = (string)$one['type'];
        $show = (string)($one['show'] ?? '');
        $name = $base.'['.$key.']';
        $fid = getLayoutFieldId($base, $key);
        $label = getConst((string)$one['label']);
        $val = $vals[$key] ?? null;
        $back = ($live !== null && ($base === 'field' || $pre !== '')) ? (($pre !== '') ? $pre.$key : $key) : '';
        $opts = [];
        foreach (is_array($one['options'] ?? null) ? $one['options'] : [] as $opt => $word) $opts[] = ['value' => (string)$opt, 'label' => getConst((string)$word)];
        $row = ['label_for' => $fid, 'label_html' => htmlspecialchars($label, ENT_QUOTES, 'UTF-8')];
        if ($show === 'seg' || $show === 'switch') {
            $now = ($type === 'bool') ? (empty($val) ? '0' : '1') : (is_bool($val) ? ($val ? '1' : '0') : (string)$val);
            $pick = ($type === 'bool') ? [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]] : $opts;
            $row = ['label_id' => $fid, 'label_html' => $row['label_html'], 'field_html' => getTplRadioGroup(['name' => $name, 'value' => $now, 'options' => $pick,
                'labelledby' => $fid, 'switch' => $type === 'bool'])];
        } elseif (in_array($type, ['focus', 'tone'], true) || ($type === 'int' && $show === 'range')) {
            $row = ['label_id' => $fid, 'label_html' => $row['label_html'], 'field_html' => $tpl->getHtmlFrag('layout-field', ['kind_focus' => $type === 'focus',
                'kind_tone' => $type === 'tone', 'kind_range' => $type === 'int', 'name_attr' => $name, 'label_id' => $fid, 'value_attr' => is_scalar($val) ? (string)$val : '',
                'min_num' => $one['min'] ?? 0, 'max_num' => $one['max'] ?? 100, 'points' => getLayoutPoints(is_string($val) ? $val : ''),
                'tones' => getLayoutTones(is_string($val) ? $val : '')])];
        } elseif ($type === 'checks') {
            $now = is_array($val) ? $val : [];
            $items = [];
            foreach (getLayoutWhere($now) as $opt => $word) {
                $items[] = ['name_attr' => $name.'[]', 'value_attr' => (string)$opt, 'label_text' => $word, 'is_checked' => in_array((string)$opt, $now, true)];
            }
            $none = $tpl->getHtmlFrag('hidden', ['name_attr' => $name.'[]', 'value_attr' => '', 'input_attr' => '']);
            $row = ['label_id' => $fid, 'label_html' => $row['label_html'], 'is_full' => true,
                'field_html' => $none.$tpl->getHtmlFrag('check-list', ['labelledby' => $fid, 'items' => $items])];
        } elseif ($type === 'nodetype') {
            $now = is_array($val) ? $val : [];
            $items = [];
            foreach (getLayoutFeedTypes() as $opt => $word) {
                $items[] = ['name_attr' => $name.'[]', 'value_attr' => $opt, 'label_text' => $word, 'is_checked' => in_array($opt, $now, true)];
            }
            $none = $tpl->getHtmlFrag('hidden', ['name_attr' => $name.'[]', 'value_attr' => '', 'input_attr' => '']);
            $row = ['label_id' => $fid, 'label_html' => $row['label_html'], 'is_full' => true, 'hint_html' => htmlspecialchars(_LAYOUT_TYPEHINT, ENT_QUOTES, 'UTF-8'),
                'hint_id' => $fid.'-hint', 'field_html' => $none.$tpl->getHtmlFrag('check-list', ['labelledby' => $fid, 'describedby' => $fid.'-hint', 'items' => $items])];
        } elseif ($type === 'blockref') {
            $now = is_scalar($val) ? (string)intval($val) : '0';
            $html = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _LAYOUT_PICKBLK, 'is_selected' => !isset($blks[$now])]);
            foreach ([_LAYOUT_FREEBLK => true, _LAYOUT_OTHERBLK => false] as $group => $free) {
                $part = '';
                foreach ($blks as $bid => $blk) {
                    if ($blk['new'] || $blk['kind'] === 'sys' || in_array('infly', $blk['which'], true) !== $free) continue;
                    $word = sprintf(_LAYOUT_NUMBERED, $bid, getConst($blk['title']));
                    $part .= $tpl->getHtmlFrag('select-option', ['value_attr' => $bid, 'label_text' => $word, 'is_selected' => $bid === $now]);
                }
                if ($part !== '') $html .= $tpl->getHtmlFrag('select-optgroup', ['label_text' => $group, 'options_html' => $part]);
            }
            $row['field_html'] = $tpl->getHtmlFrag('select', ['name_attr' => $name, 'selectid' => $fid, 'describedby' => $fid.'-hint', 'options_html' => $html]);
            $row['hint_html'] = htmlspecialchars(_LAYOUT_BLOCKHINT, ENT_QUOTES, 'UTF-8');
            $row['hint_id'] = $fid.'-hint';
        } elseif ($type === 'noderef') {
            $html = getLayoutNodeHtml(is_scalar($val) ? intval($val) : 0, '');
            $row['field_html'] = $tpl->getHtmlFrag('layout-field', ['kind_find' => true, 'select_id' => $fid, 'find_url' => getLayoutUrl(['op' => 'find', 'key' => $key]),
                'find_text' => _LAYOUT_FINDNODE,
                'select_html' => $tpl->getHtmlFrag('select', ['name_attr' => $name, 'selectid' => $fid, 'describedby' => $fid.'-hint', 'options_html' => $html])]);
            $row['hint_html'] = htmlspecialchars(_LAYOUT_NODEHINT, ENT_QUOTES, 'UTF-8');
            $row['hint_id'] = $fid.'-hint';
        } elseif ($type === 'choice') {
            $now = is_bool($val) ? ($val ? '1' : '0') : (is_scalar($val) ? (string)$val : '');
            $html = '';
            foreach ($opts as $opt) {
                $html .= $tpl->getHtmlFrag('select-option', ['value_attr' => $opt['value'], 'label_text' => $opt['label'], 'is_selected' => $opt['value'] === $now]);
            }
            $row['field_html'] = $tpl->getHtmlFrag('select', ['name_attr' => $name, 'selectid' => $fid, 'options_html' => $html]);
        } elseif ($type === 'icon') {
            $row['field_html'] = $tpl->getHtmlPart('icon-picker', ['name_attr' => $name, 'value_attr' => is_string($val) ? $val : '', 'label_text' => $label,
                'placeholder_text' => $label, 'button_label' => _LAYOUT_PICK]);
            unset($row['label_for']);
        } elseif ($type === 'url' && $addrs !== []) {
            $now = is_string($val) ? $val : '';
            $seen = false;
            $html = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _LAYOUT_NOWHERE, 'is_selected' => $now === '']);
            foreach ($addrs as $group => $opts) {
                $part = '';
                foreach ($opts as $addr => $word) {
                    $seen = $seen || $addr === $now;
                    $part .= $tpl->getHtmlFrag('select-option', ['value_attr' => $addr, 'label_text' => $word, 'is_selected' => $addr === $now]);
                }
                if ($part !== '') $html .= $tpl->getHtmlFrag('select-optgroup', ['label_text' => $group, 'options_html' => $part]);
            }
            $html .= $tpl->getHtmlFrag('select-option', ['value_attr' => '__own', 'label_text' => _LAYOUT_OWNADDR, 'is_selected' => !$seen && $now !== '']);
            $pick = 'addr-'.$fid;
            $row['field_html'] = $tpl->getHtmlFrag('layout-field', ['kind_addr' => true, 'when_name' => $pick,
                'select_html' => $tpl->getHtmlFrag('select', ['name_attr' => $pick, 'selectid' => $fid, 'options_html' => $html, 'select_attr' => 'data-sl-lay-addr']),
                'input_html' => $tpl->getHtmlFrag('input', ['name_attr' => $name, 'value_attr' => $now, 'maxlength_num' => 2048, 'placeholder_text' => 'https://',
                    'input_attr' => 'data-sl-lay-own aria-label="'.htmlspecialchars(_LAYOUT_OWNADDR, ENT_QUOTES, 'UTF-8').'"'])]);
        } elseif ($type === 'markdown' || $type === 'code') {
            $num = rtrim($pre, '-');
            $row['is_full'] = true;
            $row['field_html'] = $tpl->getHtmlFrag('textarea', ['name_attr' => '', 'input_id' => $fid, 'value_text' => is_string($val) ? $val : '', 'rows_num' => 4,
                'is_readonly' => true, 'input_attr' => 'title="'.htmlspecialchars(_LAYOUT_EDITOPEN, ENT_QUOTES, 'UTF-8').'" data-sl-lay-edit="'.$key.'"'
                .' data-sl-lay-num="'.$num.'"'])
                .$tpl->getHtmlFrag('layout-field', ['kind_edit' => true, 'edit_key' => $key, 'edit_num' => $num, 'edit_text' => _EDIT,
                    'edit_icon' => ($type === 'code') ? 'code-slash' : 'pencil-square']);
        } elseif ($type === 'file') {
            $row['field_html'] = getFileManagerField(['id' => $fid, 'place' => 'layout.attach', 'name' => $fid.'-up', 'path' => $name, 'url' => '',
                'path_value' => is_string($val) ? $val : '', 'is_nolink' => true]);
        } elseif (in_array($type, ['text', 'textarea', 'select', 'int', 'date', 'email', 'url'], true)) {
            $rule = Layout::getFieldRule($key, $type, $one);
            if ($type === 'select') {
                foreach ($rule['options']['items'] as $opt => &$item) $item['title'] = getConst((string)($one['options'][$opt] ?? $opt));
                unset($item);
            }
            $form = $fld->getFieldForm($tpl, [$key => $rule], [$key => is_bool($val) ? ($val ? '1' : '0') : $val]);
            $html = (string)($form[$key]['field_html'] ?? '');
            $row['field_html'] = str_replace(['name="field['.$key.']', 'id="f-field-'.$key.'"', 'f-field-'.$key.'-hint'], ['name="'.$name, 'id="'.$fid.'"', $fid.'-hint'], $html);
        } else {
            $row['field_html'] = $tpl->getHtmlFrag('input', ['name_attr' => $name, 'input_id' => $fid, 'value_attr' => is_scalar($val) ? (string)$val : '',
                'maxlength_num' => 2048]);
        }
        if (isset($one['when'])) $row['attr'] = 'data-sl-show-when="'.htmlspecialchars($base.'['.$one['when'][0].']', ENT_QUOTES, 'UTF-8').'" data-sl-show-value="'
            .htmlspecialchars($one['when'][1], ENT_QUOTES, 'UTF-8').'"';
        if ($back !== '') $row['hint_html'] = ($row['hint_html'] ?? '').$tpl->getHtmlFrag('layout-field', [
            'kind_back' => true, 'back_arg' => $back, 'back_text' => _LAYOUT_LIVEBACK, 'label' => $label,
            'is_same' => isLayoutSame($val, $live[$key] ?? null)]);
        $rows[] = $row;
    }
    return $rows;
}

# The nine points of focus as the grid of a picture shows them, the chosen one pressed
function getLayoutPoints(string $now): array {
    $words = explode('|', _LAYOUT_FOCUS9);
    $out = [];
    foreach (Layout::FOCUS as $i => $point) $out[] = ['value' => $point, 'label' => $words[$i] ?? $point, 'is_on' => $point === ($now ?: 'center')];
    return $out;
}

# The tones of a section as swatches of the theme, the chosen one pressed
function getLayoutTones(string $now): array {
    $words = explode('|', _LAYOUT_TONES);
    $out = [];
    foreach (Layout::TONES as $i => $tone) $out[] = ['value' => $tone, 'label' => $words[$i] ?? $tone, 'is_on' => $tone === $now];
    return $out;
}

# The rows of a list of items, each a block of the form canon with its fields, its tools and its place among the others; an item is the site's one at its place
function getLayoutItems(array $desc, mixed $list, string $owner, mixed $live = null, array $addrs = []): string {
    global $tpl;
    $out = '';
    $list = is_array($list) ? array_values($list) : [];
    $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
    $name = (string)($desc['name'] ?? '');
    $max = intval($desc['max'] ?? 0);
    foreach ($list as $i => $item) {
        $item = is_array($item) ? $item : [];
        $label = trim((string)($item[$name] ?? '')) ?: getConst((string)($desc['label'] ?? '')).' '.($i + 1);
        $cap = $tpl->getHtmlPart('layout-item', ['num' => $i, 'label' => $label, 'up_text' => _LAYOUT_UP, 'down_text' => _LAYOUT_DOWN, 'del_text' => _LAYOUT_DELITEM,
            'is_first' => $i === 0, 'is_last' => $i === count($list) - 1]);
        $out .= $tpl->getHtmlPart('toggle-form-block', ['block_id' => 'sl-lay-item-'.$owner.'-'.$i, 'label_html' => $cap,
            'content_html' => $tpl->getHtmlPart('div', ['rows' => getLayoutRows($fields, $item, 'item['.$i.']', '', is_array($live[$i] ?? null) ? $live[$i] : null, $addrs)])]);
    }
    return $tpl->getHtmlPart('layout-items', ['title' => getConst((string)($desc['label'] ?? '')), 'num' => count($list).($max ? ' / '.$max : ''), 'items_html' => $out,
        'add_text' => _LAYOUT_ADDITEM, 'is_full' => $max > 0 && count($list) >= $max]);
}

# The tree of the screen for its tab: the page with its parts, sections and blocks, the menus, or the parts of the theme
function getLayoutTree(array $dft, int $key, string $tab, string $sel, bool $oob = false): string {
    global $tpl;
    $lay = $dft['layout'];
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $types = getLayoutTypes($view);
    $link = static fn(string $one, string $to): string => getLayoutUrl(['page' => $key, 'sel' => $one, 'tab' => $to]);
    $row = static function (string $one, string $icon, string $label, string $sub, string $to, array $more = []) use ($sel, $link): array {
        $hint = $more['hint'] ?? $sub;
        unset($more['hint']);
        return $more + ['sub_title' => $hint, 'link' => ['href' => $link($one, $to), 'title' => $label, 'icon_name' => $icon, 'label' => $label, 'is_current' => $one === $sel],
            'sub_text' => $sub, 'link_attr' => 'data-sl-lay-pick="'.htmlspecialchars($one, ENT_QUOTES, 'UTF-8').'"', 'dial_title' => _ADM_ACTS];
    };
    $dial = static fn(array $acts): array => array_map(static fn(array $a): array => ['href' => '#', 'title' => $a[2], 'icon_name' => $a[1],
        'link_attr' => 'data-sl-lay-act="'.$a[0].'" data-sl-lay-id="'.htmlspecialchars((string)$a[3], ENT_QUOTES, 'UTF-8').'"'], $acts);
    $parts = static function (array $wheres) use ($places, $row, $lay): array {
        $out = [];
        foreach ($places['parts'] as $pkey => $part) {
            if (!is_array($part) || !in_array((string)($part['where'] ?? ''), $wheres, true)) continue;
            $out[] = $row('part:'.$pkey, (string)($part['icon'] ?? 'square'), getConst((string)($part['title'] ?? $pkey)), '', 'theme');
        }
        foreach ($places['menus'] as $mkey => $menu) {
            if (!is_array($menu) || !in_array((string)($menu['where'] ?? ''), $wheres, true)) continue;
            $num = 0;
            foreach (is_array($lay['menus'][$mkey] ?? null) ? $lay['menus'][$mkey] : [] as $item) $num += 1 + count(is_array($item['kids'] ?? null) ? $item['kids'] : []);
            $out[] = $row('menu:'.$mkey, 'menu-button-wide', getConst((string)($menu['title'] ?? $mkey)), sprintf(getPluralForm($num, _LAYOUT_ITEMS), $num), 'menu');
        }
        return $out;
    };
    $groups = [];
    $menus = [];
    $note = '';
    if ($tab === 'page') {
        $page = $lay['pages'][$key] ?? ['sections' => []];
        $slot = $view->getSlotName($key);
        $addr = ($key === $lay['notfound']) ? _LAYOUT_LOST : _ADDRESS.': /'.(($key === $lay['home']) ? '' : ($slot ?? (string)($page['addr'] ?? '')));
        $icon = ($key === $lay['notfound']) ? 'sign-stop' : (($key === $lay['home']) ? 'house-door' : 'file-earmark-richtext');
        $groups[] = ['rows' => [$row('page:'.$key, $icon, getLayoutPageTitle($lay, $key), $addr, 'page')]];
        $groups[] = ['title' => _LAYOUT_AT_HEAD, 'icon' => 'window', 'chip' => _LAYOUT_AT_ALL, 'rows' => $parts(['_LAYOUT_AT_TOP', '_LAYOUT_AT_HEAD', '_LAYOUT_AT_MENU'])];
        $secs = [];
        foreach ($page['sections'] as $sec) {
            $sid = intval($sec['id'] ?? 0);
            $type = (string)($sec['type'] ?? '');
            $one = $types[$type] ?? ['title' => $type, 'icon' => 'question-square', 'frag' => false];
            $sub = getLayoutSecName($sec, true);
            if (!$one['frag']) $sub = _LAYOUT_NOFRAG;
            $on = !empty($sec['on']);
            $secs[] = $row('sec:'.$sid, $one['icon'], $one['title'], $sub, 'page', ['key' => (string)$sid, 'is_drag' => true, 'is_off' => !$on,
                'dial' => $dial([['sec-toggle', $on ? 'eye-slash' : 'eye', $on ? _LAYOUT_HIDESEC : _LAYOUT_SHOWSEC, $sid], ['sec-del', 'trash3', _DELETE, $sid]])]);
        }
        $groups[] = ['title' => _LAYOUT_CONTENT, 'icon' => 'layout-text-window', 'chip' => (string)count($secs), 'rows' => $secs, 'drag' => 'sec',
            'none' => $secs ? '' : _LAYOUT_NOSECS,
            'add' => ['act' => 'lib', 'label' => _LAYOUT_ADDSEC]];
        $blks = getLayoutBlocks($dft);
        $name = 'page-'.$key;
        if ($slot !== null) $name = $slot;
        [$side, $top] = $view->getPageColumns($key);
        $open = ['l' => in_array($side, ['0', '1'], true), 'r' => in_array($side, ['0', '2'], true)];
        $open += ['c' => in_array($top, ['0', '1'], true), 'd' => in_array($top, ['0', '2'], true)];
        $free = [];
        $lead = ['title' => _LAYOUT_BLOCKS, 'icon' => 'layout-sidebar', 'chip' => _LAYOUT_AT_ALL];
        foreach ($places['zones'] as $zone => $info) {
            $rows = [];
            foreach ($blks as $bid => $blk) {
                if ($blk['bpos'] !== $zone || in_array('infly', $blk['which'], true)) continue;
                $rows[] = getLayoutBlockRow($blk, $name, $key === $lay['home'], count($rows) + 1, $row, $dial);
            }
            $groups[] = ['lead' => $lead, 'title' => getConst((string)($info['title'] ?? $zone)), 'icon' => (string)($info['icon'] ?? 'layout-sidebar'), 'is_sub' => true,
                'chip' => ($open[$zone] ?? true) ? '' : _LAYOUT_ZONEOFF,
                'rows' => $rows, 'drag' => 'blk-'.$zone, 'none' => $rows ? '' : _LAYOUT_ZONENONE];
            $lead = null;
        }
        foreach ($blks as $blk) if (in_array('infly', $blk['which'], true)) $free[] = getLayoutBlockRow($blk, $name, $key === $lay['home'], 0, $row, $dial);
        if ($free) $groups[] = ['title' => _LAYOUT_FREEBLK, 'icon' => 'pin-angle', 'is_sub' => true, 'rows' => $free];
        $groups[count($groups) - 1]['add'] = ['act' => 'blk-add', 'label' => _ADDNEWBLOCK];
        $groups[] = ['title' => _LAYOUT_AT_FOOT, 'icon' => 'window-dock', 'chip' => _LAYOUT_AT_ALL, 'rows' => $parts(['_LAYOUT_AT_FOOT'])];
    } elseif ($tab === 'menu') {
        $mkey = explode(':', $sel)[1] ?? '';
        if (!isset($places['menus'][$mkey])) $mkey = (string)(array_key_first($places['menus']) ?? '');
        foreach ($places['menus'] as $one => $menu) {
            $menus[] = ['label' => getConst((string)($menu['title'] ?? $one)), 'is_on' => $one === $mkey,
                'url' => getLayoutUrl(['op' => 'tree', 'page' => $key, 'tab' => 'menu', 'sel' => 'menu:'.$one])];
        }
        if ($mkey !== '') {
            $menu = $places['menus'][$mkey];
            $depth = intval($menu['depth'] ?? 1);
            $rows = [];
            $list = is_array($lay['menus'][$mkey] ?? null) ? $lay['menus'][$mkey] : [];
            $names = array_merge(...array_values(getLayoutAddrs($lay)));
            foreach ($list as $i => $item) {
                $rows[] = getLayoutMenuRow($mkey, $item, 0, $depth, $i === 0, $names, $row, $dial);
                foreach (is_array($item['kids'] ?? null) ? $item['kids'] : [] as $kid) {
                    $rows[] = getLayoutMenuRow($mkey, $kid, intval($item['id'] ?? 0), $depth, false, $names, $row, $dial);
                }
            }
            $groups[] = ['title' => getConst((string)($menu['where'] ?? '')), 'icon' => 'geo', 'chip' => ($depth > 1) ? _LAYOUT_DEPTH2 : _LAYOUT_DEPTH1, 'rows' => $rows,
                'drag' => 'menu-'.$mkey, 'none' => $rows ? '' : _LAYOUT_NOITEMS, 'add' => ['act' => 'menu-add', 'label' => _LAYOUT_ADDITEM, 'arg' => $mkey]];
            $note = ($depth > 1) ? _LAYOUT_MENUHINT : _LAYOUT_MENUHINT1;
        }
    } else {
        $where = [];
        foreach ($places['parts'] as $pkey => $part) if (is_array($part)) $where[(string)($part['where'] ?? '')][] = $pkey;
        foreach ($where as $wkey => $keys) {
            $rows = [];
            foreach ($keys as $pkey) {
                $part = $places['parts'][$pkey];
                $rows[] = $row('part:'.$pkey, (string)($part['icon'] ?? 'square'), getConst((string)($part['title'] ?? $pkey)), '', 'theme');
            }
            $groups[] = ['title' => getConst($wkey), 'icon' => 'geo', 'rows' => $rows];
        }
        if (!$groups) $groups[] = ['none' => _LAYOUT_NOPARTS, 'rows' => []];
        $note = _LAYOUT_THEMEHINT;
    }
    $tabs = [];
    foreach ([['page', 'file-earmark-richtext', _PAGE], ['menu', 'menu-button-wide', _LAYOUT_MENUS], ['theme', 'palette', _LAYOUT_THEME]] as [$one, $icon, $label]) {
        $tabs[] = ['label' => $label, 'icon' => $icon, 'is_on' => $one === $tab, 'url' => getLayoutUrl(['op' => 'tree', 'page' => $key, 'tab' => $one, 'sel' => $sel])];
    }
    $last = array_slice($lay['pages'][$key]['sections'] ?? [], -1)[0]['type'] ?? '';
    return $tpl->getHtmlPart('layout-tree', ['is_oob' => $oob, 'tab' => $tab, 'tail' => ($last !== '') ? (string)($types[$last]['title'] ?? $last) : '', 'tabs' => $tabs,
        'tabs_label' => _LAYOUT_WHAT, 'menus' => $menus, 'menus_label' => _LAYOUT_MENUS,
        'groups' => $groups, 'note_html' => ($note !== '') ? $tpl->getHtmlFrag('alert', ['text' => $note, 'meta' => '', 'type' => 'info', 'is_warn' => false]) : '']);
}

# One row of a block in the tree: its kind, its state and whether this page shows it, its order in the zone, none for a free block
function getLayoutBlockRow(array $blk, string $pname, bool $phome, int $num, Closure $row, Closure $dial): array {
    global $name, $home;
    [$was, $washome] = [$name, $home];
    [$name, $home] = [$pname, $phome ? 1 : 0];
    $free = in_array('infly', $blk['which'], true);
    $icon = match ($blk['kind']) {
        'file' => 'file-earmark-code',
        'rss' => 'rss',
        'sys' => 'gear',
        default => 'code-square',
    };
    $sub = match ($blk['kind']) {
        'file' => $blk['bfile'],
        'rss' => 'RSS',
        'sys' => _LAYOUT_SYSBLK,
        default => 'HTML',
    };
    $here = $free || isBlockShown($blk['which'] ?: ['all']);
    [$name, $home] = [$was, $washome];
    if (!$blk['status']) $sub .= ' · '._LAYOUT_OFF;
    if ($free) $sub .= ' · №'.$blk['id'];
    elseif (!$here) $sub .= ' · '._LAYOUT_NOTHERE;
    $on = $blk['status'];
    return $row('blk:'.$blk['id'], $icon, getConst($blk['title']) ?: $blk['bfile'], $sub, 'page', ['key' => $blk['id'], 'is_drag' => !$free, 'is_off' => !$on || !$here,
        'order_num' => $num ?: '', 'order_title' => _SORTORDER,
        'dial' => $dial([['blk-toggle', $on ? 'eye-slash' : 'eye', $on ? _LAYOUT_BLKOFF : _LAYOUT_BLKON, $blk['id']], ['blk-del', 'trash3', _DELETE, $blk['id']]])]);
}

# One row of a menu item in the tree: the name of its page or module over its path, the way to nest it or to bring it a level up, its deletion
function getLayoutMenuRow(string $mkey, array $item, int $up, int $depth, bool $first, array $names, Closure $row, Closure $dial): array {
    $iid = intval($item['id'] ?? 0);
    $href = (string)($item['href'] ?? '');
    $acts = [];
    if ($depth > 1 && $up) $acts[] = ['menu-out', 'arrow-bar-left', _LAYOUT_OUT, $iid];
    if ($depth > 1 && !$up && !$first && empty($item['kids'])) $acts[] = ['menu-in', 'arrow-bar-right', _LAYOUT_IN, $iid];
    $acts[] = ['menu-del', 'trash3', _LAYOUT_DELITEM, $iid];
    $icon = (string)($item['icon'] ?? '') ?: ($up ? 'arrow-return-right' : 'link-45deg');
    $grp = (string)$up;
    $attr = 'data-sl-drag-group="'.$grp.'" data-sl-drag-scope="'.$grp.'"'.($up ? ' data-sl-drag-parent="'.$up.'"' : '');
    return $row('menu:'.$mkey.':'.$iid, $icon, trim((string)($item['title'] ?? '')) ?: _LAYOUT_NEWITEM, ($href !== '') ? ($names[$href] ?? $href) : _LAYOUT_DROPITEM, 'menu',
        ['hint' => $href, 'key' => (string)$iid, 'is_drag' => true, 'row_attr' => $attr, 'is_kid' => $up > 0, 'is_off' => intval($item['who'] ?? 0) === 2, 'dial' => $dial($acts)]);
}

# The inspector of the pick: its head, its form by tabs and its foot; nothing picked shows the start of the screen
function getLayoutInsp(array $dft, int $key, string $sel, bool $oob = false): string {
    global $tpl, $conf;
    $lay = $dft['layout'];
    $live = Layout::getLayoutData($conf['layout'] ?? []);
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $part = explode(':', $sel);
    [$prev, $next] = getLayoutSteps($dft, $places, $sel);
    $data = ['is_oob' => $oob, 'save_url' => getLayoutUrl(['op' => 'save']), 'token' => getSiteToken('layout'), 'sel' => $sel, 'page' => $key, 'aria_text' => _LAYOUT_PICKED,
        'unpick_text' => _LAYOUT_UNPICK, 'tabs' => [], 'body_html' => '', 'groups' => [], 'main' => [], 'danger' => [], 'prev' => $prev, 'next' => $next,
        'is_step' => $prev !== '' || $next !== '', 'prev_text' => _BACK, 'next_text' => _NEXT];
    $note = static fn(string $text): string => $tpl->getHtmlFrag('alert', ['text' => $text, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    $grid = static fn(array $rows): string => $rows ? $tpl->getHtmlPart('div', ['rows' => $rows]) : '';
    $addrs = getLayoutAddrs($lay);
    if ($part[0] === 'sec' && ($at = getLayoutSecPlace($lay, intval($part[1]))) !== null) {
        $sec = $lay['pages'][$at[0]]['sections'][$at[1]];
        $sid = intval($sec['id']);
        $was = getLayoutSecPlace($live, $sid);
        $was = ($was !== null) ? $live['pages'][$was[0]]['sections'][$was[1]] : null;
        $types = getLayoutTypes($view);
        $type = (string)($sec['type'] ?? '');
        $one = $types[$type] ?? null;
        if ($one === null) return getLayoutInsp($dft, $key, '', $oob);
        $desc = $one['desc'];
        $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
        $blks = in_array('blockref', array_column($fields, 'type'), true) ? getLayoutBlocks($dft) : [];
        $body = $grid(getLayoutRows($fields, $sec, 'field', '', $was, $addrs, $blks));
        if (is_array($desc['items'] ?? null)) $body .= getLayoutItems($desc['items'], $sec['items'] ?? [], 's'.$sid, ($was !== null) ? ($was['items'] ?? []) : null, $addrs);
        if ($type === Layout::SLOT) $body .= $note(sprintf(_LAYOUT_SLOTNOTE, getLayoutSecName($sec)));
        if (!$one['frag']) $body .= $note(_LAYOUT_NOFRAGIN);
        $data['tabs'][] = ['key' => 'cont', 'icon' => 'card-text', 'label' => _LAYOUT_TABCONT, 'body_html' => $body, 'is_first' => true];
        $look = $grid(getLayoutRows($fields, $sec, 'field', 'look', $was, $addrs));
        if ($look !== '') $data['tabs'][] = ['key' => 'look', 'icon' => 'sliders', 'label' => _LAYOUT_TABLOOK, 'body_html' => $look, 'is_first' => false];
        $show = ['who' => '0', 'lang' => '', 'from' => '', 'till' => '', 'anchor' => '', 'phone' => true, 'desk' => true];
        $rows = getLayoutRows(getLayoutShowFields(), array_replace($show, $sec), 'field', '', ($was !== null) ? array_replace($show, $was) : null);
        $data['tabs'][] = ['key' => 'show', 'icon' => 'eye', 'label' => _LAYOUT_TABSHOW, 'is_first' => false, 'body_html' => $grid($rows).$note(_LAYOUT_SHOWNOTE)];
        $on = !empty($sec['on']);
        $list = $lay['pages'][$at[0]]['sections'];
        $copy = $one['one'] ? [] : [['act' => 'sec-dup', 'icon' => 'copy', 'title' => _LAYOUT_DUP, 'id' => $sid],
            ['open' => 'dest', 'icon' => 'files', 'title' => _LAYOUT_TOPAGE, 'id' => $sid],
            ['act' => 'sec-mine', 'icon' => 'bookmark-plus', 'title' => _LAYOUT_TOMINE, 'id' => $sid]];
        $move = [['act' => 'sec-up', 'icon' => 'arrow-up', 'title' => _LAYOUT_UP, 'id' => $sid, 'is_off' => $at[1] === 0],
            ['act' => 'sec-down', 'icon' => 'arrow-down', 'title' => _LAYOUT_DOWN, 'id' => $sid, 'is_off' => $at[1] === count($list) - 1]];
        $keys = getLayoutLookKeys($desc);
        $words = [];
        foreach ($fields as $fld) if (in_array($fld['key'] ?? '', $keys, true)) $words[] = mb_strtolower(getConst((string)$fld['label']));
        $looks = $keys ? [['look' => $type, 'icon' => 'paint-bucket', 'title' => _LAYOUT_LOOKCOPY, 'id' => $sid, 'done' => sprintf(_LAYOUT_LOOKTAKEN, implode(', ', $words))],
            ['paste' => $type, 'act' => 'look-paste', 'icon' => 'clipboard-check', 'title' => sprintf(_LAYOUT_LOOKWAIT, $one['title']), 'ready' => _LAYOUT_LOOKPASTE, 'id' => $sid,
                'is_off' => true]] : [];
        $last = [];
        $anchor = (string)($sec['anchor'] ?? '');
        if (preg_match(Layout::ANCHOR, $anchor) === 1) $last[] = ['copy' => getLayoutPagePath($lay, $at[0]).'#'.$anchor, 'icon' => 'link-45deg', 'title' => _LAYOUT_SECLINK,
            'done' => _COPIED];
        $last[] = ['act' => 'sec-toggle', 'icon' => $on ? 'eye-slash' : 'eye', 'title' => $on ? _LAYOUT_HIDESEC : _LAYOUT_SHOWSEC, 'id' => $sid];
        $data['groups'] = array_values(array_filter([$copy, $move, $looks, $last]));
        $data['danger'] = ['act' => 'sec-del', 'label' => _DELETE, 'id' => $sid];
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => $one['icon'], 'title_text' => $one['title'],
            'sub_text' => sprintf(_LAYOUT_SECOF, getLayoutPageTitle($lay, $at[0]))]));
    }
    if ($part[0] === 'part' && is_array($places['parts'][$part[1]] ?? null)) {
        $one = $places['parts'][$part[1]];
        $fields = is_array($one['fields'] ?? null) ? $one['fields'] : [];
        $vals = $lay['theme'];
        $body = '';
        if (is_array($one['conf'] ?? null) && in_array('sitename', $one['conf'], true)) $body .= $grid(getLayoutConfRows($dft));
        $body .= $grid(getLayoutRows($fields, $vals, 'field', '', $live['theme'], $addrs));
        $list = is_array($one['items'] ?? null) ? $one['items'] : [];
        $lkey = (string)($list['key'] ?? '');
        if (preg_match(Layout::FIELD, $lkey) === 1) $body .= getLayoutItems($list, $vals[$lkey] ?? [], 'p'.$part[1], $live['theme'][$lkey] ?? [], $addrs);
        if (($one['note'] ?? '') !== '') $body .= $note(getConst((string)$one['note']));
        $where = getConst((string)($one['where'] ?? ''));
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => (string)($one['icon'] ?? 'square'),
            'title_text' => getConst((string)($one['title'] ?? $part[1])),
            'sub_text' => ($where === _LAYOUT_AT_ALL) ? $where : $where.' · '._LAYOUT_ALLSITE, 'body_html' => $body]));
    }
    if ($part[0] === 'menu' && is_array($places['menus'][$part[1]] ?? null)) {
        $menu = $places['menus'][$part[1]];
        $list = is_array($lay['menus'][$part[1]] ?? null) ? $lay['menus'][$part[1]] : [];
        $title = getConst((string)($menu['title'] ?? $part[1]));
        $where = getConst((string)($menu['where'] ?? ''));
        if (!isset($part[2]) || ($at = getLayoutItemPlace($list, intval($part[2]))) === null) {
            $data['main'] = ['act' => 'menu-add', 'arg' => $part[1], 'icon' => 'plus-lg', 'label' => _LAYOUT_ADDITEM];
            return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'menu-button-wide', 'title_text' => $title, 'sub_text' => $where.' · '._LAYOUT_ALLSITE,
                'body_html' => $note(_LAYOUT_MENUNOTE)]));
        }
        $item = isset($at[1]) ? $list[$at[0]]['kids'][$at[1]] : $list[$at[0]];
        $olds = is_array($live['menus'][$part[1]] ?? null) ? $live['menus'][$part[1]] : [];
        $was = getLayoutItemPlace($olds, intval($part[2]));
        $was = ($was === null) ? null : (isset($was[1]) ? $olds[$was[0]]['kids'][$was[1]] : $olds[$was[0]]);
        $body = $grid(getLayoutRows(getLayoutItemFields($menu), $item, 'field', '', $was, $addrs));
        $kids = count(is_array($item['kids'] ?? null) ? $item['kids'] : []);
        if ($kids) $body .= $note(sprintf(_LAYOUT_KIDSNOTE, $kids));
        $data['danger'] = ['act' => 'menu-del', 'label' => _LAYOUT_DELITEM, 'id' => intval($item['id'])];
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'link-45deg', 'title_text' => _LAYOUT_MITEM, 'sub_text' => $title, 'body_html' => $body]));
    }
    if ($part[0] === 'blk' && ($blk = getLayoutBlocks($dft)[$part[1]] ?? null) !== null) {
        $was = $blk['new'] ? null : (getLayoutBlocks(['blocks' => []])[$part[1]] ?? null);
        $body = $grid(getLayoutRows(getLayoutBlockFields($blk, $places['zones']), $blk, 'field', '', $was));
        $body .= $note($blk['kind'] === 'sys' ? _LAYOUT_SYSNOTE : _LAYOUT_BLKNOTE);
        $on = $blk['status'];
        $acts = [['act' => 'blk-toggle', 'icon' => $on ? 'eye-slash' : 'eye', 'title' => $on ? _LAYOUT_BLKOFF : _LAYOUT_BLKON, 'id' => $blk['id']]];
        if (!$blk['new']) $acts[] = ['href' => getLayoutBlockUrl($blk['id']), 'icon' => 'box-arrow-up-right', 'title' => _LAYOUT_INBLOCKS];
        $data['groups'] = [$acts];
        if ($blk['kind'] !== 'sys') $data['danger'] = ['act' => 'blk-del', 'label' => _LAYOUT_DELBLK, 'id' => $blk['id']];
        $free = in_array('infly', $blk['which'], true);
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'bricks', 'title_text' => _LAYOUT_BLOCK,
            'sub_text' => $free ? sprintf(_LAYOUT_FREENUM, $blk['id']) : _LAYOUT_BLOCKS.' · '._LAYOUT_ALLSITE, 'body_html' => $body]));
    }
    if ($part[0] === 'page' && isset($lay['pages'][intval($part[1])])) {
        $pkey = intval($part[1]);
        $page = $lay['pages'][$pkey];
        $base = ['title' => '', 'addr' => '', 'desc' => '', 'og' => '', 'noindex' => '0', 'who' => '0', 'band' => true, 'side' => '3', 'top' => '3'];
        $vals = array_replace($base, $page, ['noindex' => empty($page['noindex']) ? '0' : '1']);
        $was = is_array($live['pages'][$pkey] ?? null) ? array_replace($base, $live['pages'][$pkey], ['noindex' => empty($live['pages'][$pkey]['noindex']) ? '0' : '1']) : null;
        $slot = $view->getSlotName($pkey);
        $lost = $pkey === $lay['notfound'];
        $body = $grid(getLayoutRows(getLayoutPageFields($lay, $pkey), $vals, 'field', '', $was));
        $say = ($pkey === $lay['home']) ? _LAYOUT_HOMENOTE : _LAYOUT_OWNNOTE;
        if ($slot !== null) $say = sprintf(_LAYOUT_SLOTPAGE, ($slot === '') ? _HOME : getModuleName($slot));
        if ($lost) $say = _LAYOUT_LOSTNOTE;
        $body .= $note($say).$note(_LAYOUT_COLSNOTE);
        if (!$lost) $data['groups'] = [[['act' => 'page-copy', 'icon' => 'files', 'title' => _LAYOUT_PAGECOPY, 'id' => $pkey]]];
        if (!$lost && $pkey !== $lay['home']) $data['danger'] = ['act' => 'page-del', 'label' => _LAYOUT_DELPAGE, 'id' => $pkey];
        $title = $lost ? _LAYOUT_PAGE404 : (($pkey === $lay['home']) ? _HOME : _PAGE);
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => $lost ? 'sign-stop' : 'file-earmark-richtext', 'title_text' => $title,
            'sub_text' => sprintf(_LAYOUT_SECNUM, count($page['sections'])), 'body_html' => $body]));
    }
    $data['sel'] = '';
    $data['main'] = ['open' => 'ready', 'icon' => 'file-earmark-plus', 'label' => $lay['shipped'] ? _LAYOUT_READYPICK : _LAYOUT_NEWPAGE];
    $keys = [[['Ctrl', 'Z'], _LAYOUT_KEYUNDO], [['Ctrl', 'Shift', 'Z'], _LAYOUT_KEYREDO], [['Ctrl', 'D'], _LAYOUT_DUP], [['Alt', '↑'], _LAYOUT_UP],
        [['Alt', '↓'], _LAYOUT_DOWN], [['Delete'], _DELETE], [['Ctrl', 'K'], _LAYOUT_CMDS], [['Ctrl', 'Alt', 'P'], _LAYOUT_KEYVIEW], [['Esc'], _LAYOUT_UNPICK]];
    $keys = $tpl->getHtmlPart('layout-keys', ['keys' => array_map(static fn(array $v): array => ['keys' => $v[0], 'text' => $v[1]], $keys)]);
    return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'cursor', 'title_text' => _LAYOUT_NOPICK, 'sub_text' => _LAYOUT_NOPICK2,
        'body_html' => ($lay['shipped'] ? $note(_LAYOUT_SHIPNOTE) : '').$note(_LAYOUT_PICKHINT).$keys]));
}

# The part before and after the pick in the order of its tree: the sections of its page, the items of its menu, the blocks by their zones and then the free ones
function getLayoutSteps(array $dft, array $places, string $sel): array {
    $lay = $dft['layout'];
    $part = explode(':', $sel);
    $list = [];
    if ($part[0] === 'sec' && ($at = getLayoutSecPlace($lay, intval($part[1] ?? 0))) !== null) {
        foreach ($lay['pages'][$at[0]]['sections'] as $sec) $list[] = 'sec:'.intval($sec['id'] ?? 0);
    } elseif ($part[0] === 'menu' && isset($part[2])) {
        foreach (array_keys(getLayoutFlat($lay['menus'][$part[1]] ?? [])) as $iid) $list[] = 'menu:'.$part[1].':'.$iid;
    } elseif ($part[0] === 'blk') {
        $blks = getLayoutBlocks($dft);
        foreach (array_keys($places['zones']) as $zone) {
            foreach ($blks as $bid => $blk) if ($blk['bpos'] === (string)$zone && !in_array('infly', $blk['which'], true)) $list[] = 'blk:'.$bid;
        }
        foreach ($blks as $bid => $blk) if (in_array('infly', $blk['which'], true)) $list[] = 'blk:'.$bid;
    }
    $at = array_search($sel, $list, true);
    if ($at === false || count($list) < 2) return ['', ''];
    return [$list[$at - 1] ?? '', $list[$at + 1] ?? ''];
}

# The items of a menu flat by id in the order of its tree, each carrying the ids of its kids in place of them
function getLayoutFlat(mixed $list): array {
    $out = [];
    foreach (is_array($list) ? $list : [] as $item) {
        if (!is_array($item)) continue;
        $kids = is_array($item['kids'] ?? null) ? $item['kids'] : [];
        $out[intval($item['id'] ?? 0)] = array_replace($item, ['kids' => array_map('intval', array_column($kids, 'id'))]);
        foreach ($kids as $kid) if (is_array($kid)) $out[intval($kid['id'] ?? 0)] = array_replace($kid, ['kids' => []]);
    }
    return $out;
}

# The address of a page of the draft on the site: the root for the home page, the module's own for a page holding its slot, else its path
function getLayoutPagePath(array $lay, int $key): string {
    $slot = getLayoutView($lay)->getSlotName($key);
    if ($key === $lay['home']) return HOME_PATH;
    if ($slot !== null && $slot !== '') return getSeoUrl(['name' => $slot]);
    return HOME_PATH.(string)($lay['pages'][$key]['addr'] ?? '');
}

# The look of a section: the settings of its tab of the look that shape it, never a picture, its description, its point of focus or an icon, which are its content
function getLayoutLookKeys(array $desc): array {
    $out = [];
    foreach (is_array($desc['fields'] ?? null) ? $desc['fields'] : [] as $one) {
        if (is_array($one) && ($one['tab'] ?? '') === 'look' && in_array($one['type'] ?? '', ['select', 'bool', 'int', 'tone'], true)) $out[] = (string)$one['key'];
    }
    return $out;
}

# The changes of the draft against the site by the ids every page, section, item and block keeps: new, changed, gone and moved, each with the pick that leads to it
function getLayoutChanges(array $dft, array $live, array $types, array $places): array {
    $out = [];
    $now = $dft['layout'];
    $add = static function (string $icon, string $text, string $where, string $sel = '', int $page = 0) use (&$out): void {
        $out[] = ['icon' => $icon, 'text' => $text, 'where' => $where, 'sel' => $sel, 'page' => $page];
    };
    $label = static fn(array $sec): string => getLayoutSecLabel($sec, $types);
    $order = static fn(array $one, array $two): bool => array_values(array_intersect($one, $two)) === array_values(array_intersect($two, $one));
    foreach ($now['pages'] as $key => $page) {
        $title = getLayoutPageTitle($now, $key);
        $was = $live['pages'][$key] ?? null;
        if (!is_array($was)) {
            $add('file-earmark-plus', sprintf(_LAYOUT_CH_PAGE, $title), _LAYOUT_PAGES, 'page:'.$key, $key);
            continue;
        }
        $bare = static fn(array $v): array => array_diff_key($v, ['sections' => true]);
        if ($bare($page) != $bare($was)) $add('file-earmark-richtext', _LAYOUT_CH_PAGESET, $title, 'page:'.$key, $key);
        $olds = [];
        foreach ($was['sections'] as $sec) if (is_array($sec)) $olds[intval($sec['id'] ?? 0)] = $sec;
        $ids = [];
        foreach ($page['sections'] as $sec) {
            $sid = intval($sec['id'] ?? 0);
            $ids[] = $sid;
            if (!isset($olds[$sid])) $add('plus-square', sprintf(_LAYOUT_CH_SECNEW, $label($sec)), $title, 'sec:'.$sid, $key);
            elseif ($olds[$sid] != $sec) $add('pencil-square', sprintf(_LAYOUT_CH_SECSET, $label($sec)), $title, 'sec:'.$sid, $key);
        }
        foreach ($olds as $sid => $sec) if (!in_array($sid, $ids, true)) $add('dash-square', sprintf(_LAYOUT_CH_SECDEL, $label($sec)), $title, '', $key);
        if (!$order(array_keys($olds), $ids)) $add('arrow-down-up', _LAYOUT_CH_SECORD, $title, 'page:'.$key, $key);
    }
    foreach (array_keys(array_diff_key($live['pages'], $now['pages'])) as $key) {
        $add('file-earmark-minus', sprintf(_LAYOUT_CH_PAGEDEL, getLayoutPageTitle($live, $key)), _LAYOUT_PAGES);
    }
    $touched = array_filter(array_keys($now['theme'] + $live['theme']), static fn($v): bool => !isLayoutSame($now['theme'][$v] ?? null, $live['theme'][$v] ?? null));
    $site = $dft['conf'] !== [];
    foreach ($places['parts'] as $pkey => $part) {
        if (!is_array($part)) continue;
        $own = array_column(is_array($part['fields'] ?? null) ? $part['fields'] : [], 'key');
        if (is_array($part['items'] ?? null)) $own[] = (string)($part['items']['key'] ?? '');
        $conf = is_array($part['conf'] ?? null) && array_intersect($part['conf'], array_keys($dft['conf']));
        if (!array_intersect($own, $touched) && !$conf) continue;
        $site = $site && !$conf;
        $add((string)($part['icon'] ?? 'square'), sprintf(_LAYOUT_CH_PART, getConst((string)($part['title'] ?? $pkey))), getConst((string)($part['where'] ?? '')), 'part:'.$pkey);
    }
    if ($site) $add('badge-tm', _LAYOUT_CH_SITE, _LAYOUT_AT_ALL);
    foreach ($places['menus'] as $mkey => $menu) {
        if (!is_array($menu)) continue;
        $title = getConst((string)($menu['title'] ?? $mkey));
        $was = getLayoutFlat($live['menus'][$mkey] ?? []);
        $is = getLayoutFlat($now['menus'][$mkey] ?? []);
        foreach ($is as $iid => $item) {
            $word = trim((string)($item['title'] ?? ''));
            if (!isset($was[$iid])) $add('plus-lg', sprintf(_LAYOUT_CH_ITEMNEW, $word), $title, 'menu:'.$mkey.':'.$iid);
            elseif ($was[$iid] != $item) $add('pencil-square', sprintf(_LAYOUT_CH_ITEMSET, $word), $title, 'menu:'.$mkey.':'.$iid);
        }
        foreach ($was as $iid => $item) if (!isset($is[$iid])) $add('dash-lg', sprintf(_LAYOUT_CH_ITEMDEL, trim((string)($item['title'] ?? ''))), $title, 'menu:'.$mkey);
        if (!$order(array_keys($was), array_keys($is))) $add('arrow-down-up', _LAYOUT_CH_ITEMORD, $title, 'menu:'.$mkey);
    }
    foreach ($dft['blocks'] as $bid => $set) {
        if (!is_array($set)) continue;
        $was = is_array($dft['bwas'][$bid] ?? null) ? $dft['bwas'][$bid] : [];
        $word = getConst((string)(($set['title'] ?? '') ?: ($was['title'] ?? $bid)));
        if (!empty($set['new'])) $add('plus-square', sprintf(_LAYOUT_CH_BLKNEW, $word), _LAYOUT_BLOCKS, 'blk:'.$bid);
        elseif (!empty($set['del'])) $add('dash-square', sprintf(_LAYOUT_CH_BLKDEL, $word), _LAYOUT_BLOCKS);
        else $add('pencil-square', sprintf(_LAYOUT_CH_BLKSET, $word), _LAYOUT_BLOCKS, 'blk:'.$bid);
    }
    return $out;
}

# What a visitor would trip over in the draft, each remark with the pick that leads to it; $answer tells a path the site answers, $weight the bytes of a picture of the uploads
function getLayoutIssues(array $dft, array $types, array $places, Closure $answer, Closure $weight): array {
    $out = [];
    $lay = $dft['layout'];
    $day = date('Y-m-d');
    $add = static function (string $icon, string $text, string $where, string $sel, int $page) use (&$out): void {
        $out[] = ['icon' => $icon, 'text' => $text, 'where' => $where, 'sel' => $sel, 'page' => $page];
    };
    $link = static function (string $url, ?array $marks) use ($answer): string {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('#^(?:https?://|mailto:|tel:)(.*)$#is', $url, $hit) === 1) return ($hit[1] === '') ? sprintf(_LAYOUT_IS_ADDR, $url) : '';
        if (str_starts_with($url, '//')) return '';
        if ($url[0] === '#') return ($marks === null || in_array(substr($url, 1), $marks, true)) ? '' : sprintf(_LAYOUT_IS_ANCHOR, $url);
        $path = (string)parse_url($url, PHP_URL_PATH);
        return ($path === '' || $answer($path)) ? '' : sprintf(_LAYOUT_IS_ADDR, $url);
    };
    $pics = static function (array $fields, array $vals, ?array $marks, Closure $put) use ($link, $weight): void {
        $alt = in_array('alt', array_column($fields, 'key'), true) && trim((string)($vals['alt'] ?? '')) === '';
        foreach ($fields as $one) {
            $key = (string)($one['key'] ?? '');
            $val = $vals[$key] ?? '';
            if (isset($one['when']) && !isLayoutSame($vals[$one['when'][0]] ?? null, $one['when'][1])) continue;
            if (($one['type'] ?? '') === 'url' && is_string($val) && ($say = $link($val, $marks)) !== '') $put('link-45deg', $say);
            $btn = isset($one['for']) ? trim((string)($vals[$one['for']] ?? '')) : '';
            if ($btn !== '' && trim((string)$val) === '') $put('link-45deg', sprintf(_LAYOUT_IS_NOLINK, $btn));
            if (($one['type'] ?? '') === 'video' && trim((string)$val) === '') $put('play-btn', _LAYOUT_IS_NOFILM);
            if (($one['type'] ?? '') === 'page' && preg_match('#^https://#i', trim((string)$val)) !== 1) $put('window-stack', _LAYOUT_IS_HTTPS);
            if (($one['type'] ?? '') !== 'file' || !is_string($val) || $val === '') continue;
            $name = basename($val);
            if ($alt) $put('image', sprintf(_LAYOUT_IS_ALT, $name));
            if (($size = $weight($val)) > LAYOUT_HEAVY) $put('file-earmark-image', sprintf(_LAYOUT_IS_HEAVY, $name, (int)ceil($size / 1024)));
            $alt = false;
        }
        foreach ($fields as $one) {
            if (($one['type'] ?? '') !== 'markdown' || !is_string($vals[$one['key'] ?? ''] ?? null)) continue;
            preg_match_all('/(!?)\[([^\]]*)\]\(\s*<?([^\s)>]+)>?(?:\s+"[^"]*")?\s*\)/u', $vals[$one['key']], $hits, PREG_SET_ORDER);
            foreach ($hits as [, $bang, $words, $url]) {
                if ($bang === '' && ($say = $link($url, $marks)) !== '') $put('link-45deg', _LAYOUT_IS_INTEXT.' '.mb_lcfirst($say));
                if ($bang === '') continue;
                $name = basename((string)parse_url($url, PHP_URL_PATH));
                if (trim($words) === '') $put('image', sprintf(_LAYOUT_IS_MDALT, $name));
                if (($size = $weight($url)) > LAYOUT_HEAVY) $put('file-earmark-image', sprintf(_LAYOUT_IS_HEAVY, $name, (int)ceil($size / 1024)));
            }
        }
    };
    $addrs = [];
    $blks = null;
    foreach ($lay['pages'] as $key => $page) {
        $ptitle = getLayoutPageTitle($lay, $key);
        $marks = array_values(array_filter(array_map(static fn($v): string => is_array($v) ? (string)($v['anchor'] ?? '') : '', $page['sections'])));
        foreach ($page['sections'] as $sec) {
            $one = $types[(string)($sec['type'] ?? '')] ?? null;
            if ($one === null) continue;
            $where = $ptitle.' · '.$one['title'];
            $put = static fn(string $icon, string $text) => $add($icon, $text, $where, 'sec:'.intval($sec['id'] ?? 0), $key);
            $desc = $one['desc'];
            $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
            $kinds = array_column($fields, 'type', 'key');
            if (isset($kinds['title']) && !in_array('noderef', $kinds, true) && trim((string)($sec['title'] ?? '')) === '') $put('type', _LAYOUT_IS_NOTITLE);
            foreach ($kinds as $fkey => $kind) {
                $ref = (string)intval($sec[$fkey] ?? 0);
                if ($kind === 'noderef' && getLayoutNodes(intval($ref), '') === []) $put('file-earmark-richtext', _LAYOUT_IS_NONODE);
                if ($kind === 'blockref') $blks ??= array_filter(getLayoutBlocks($dft), static fn(array $v): bool => !$v['new'] && $v['kind'] !== 'sys');
                if ($kind === 'blockref' && !isset($blks[$ref])) $put('bricks', _LAYOUT_IS_NOBLOCK);
                elseif ($kind === 'blockref' && !$blks[$ref]['status']) $put('bricks', sprintf(_LAYOUT_IS_BLKOFF, getConst($blks[$ref]['title'])));
            }
            $pics($fields, $sec, $marks, $put);
            $list = is_array($desc['items'] ?? null) ? $desc['items'] : null;
            if ($list !== null && !($sec['items'] ?? [])) $put('list-ul', _LAYOUT_IS_NOITEMS);
            foreach (($list !== null && is_array($sec['items'] ?? null)) ? $sec['items'] : [] as $item) {
                if (is_array($item)) $pics(is_array($list['fields'] ?? null) ? $list['fields'] : [], $item, $marks, $put);
            }
            $anchor = (string)($sec['anchor'] ?? '');
            if ($anchor !== '' && count(array_keys($marks, $anchor, true)) > 1) $put('hash', sprintf(_LAYOUT_IS_TWIN, $anchor));
            [$from, $till] = [(string)($sec['from'] ?? ''), (string)($sec['till'] ?? '')];
            if ($from !== '' && $till !== '' && $till < $from) $put('calendar-x', _LAYOUT_IS_DATES);
            elseif ($till !== '' && $till < $day) $put('calendar-x', _LAYOUT_IS_EXPIRED);
            if (array_key_exists('phone', $sec) && array_key_exists('desk', $sec) && empty($sec['phone']) && empty($sec['desk'])) $put('eye-slash', _LAYOUT_IS_NODEV);
            if (empty($sec['on'])) $put('eye-slash', _LAYOUT_IS_HIDDEN);
        }
        $addr = (string)($page['addr'] ?? '');
        $own = !in_array($key, [$lay['home'], $lay['notfound']], true) && getLayoutView($lay)->getSlotName($key) === null;
        if ($own && $addr !== '' && isset($addrs[$addr])) $add('signpost-2', sprintf(_LAYOUT_IS_TWINURL, $addr, $addrs[$addr]), $ptitle, 'page:'.$key, $key);
        if ($own && $addr !== '') $addrs[$addr] = $ptitle;
        if ($own && trim((string)($page['desc'] ?? '')) === '') $add('search', _LAYOUT_IS_NODESC, $ptitle, 'page:'.$key, $key);
    }
    foreach ($places['parts'] as $pkey => $part) {
        if (!is_array($part)) continue;
        $put = static fn(string $icon, string $text) => $add($icon, $text, getConst((string)($part['title'] ?? $pkey)), 'part:'.$pkey, 0);
        $pics(is_array($part['fields'] ?? null) ? $part['fields'] : [], $lay['theme'], null, $put);
        $list = is_array($part['items'] ?? null) ? $part['items'] : [];
        foreach (is_array($lay['theme'][$list['key'] ?? ''] ?? null) ? $lay['theme'][$list['key']] : [] as $item) {
            if (is_array($item)) $pics(is_array($list['fields'] ?? null) ? $list['fields'] : [], $item, null, $put);
        }
    }
    foreach ($places['menus'] as $mkey => $menu) {
        $title = getConst((string)(is_array($menu) ? ($menu['title'] ?? $mkey) : $mkey));
        foreach (getLayoutFlat($lay['menus'][$mkey] ?? []) as $iid => $item) {
            $sel = 'menu:'.$mkey.':'.$iid;
            $word = trim((string)($item['title'] ?? ''));
            $href = trim((string)($item['href'] ?? ''));
            if ($word === '') $add('menu-button-wide', _LAYOUT_IS_NONAME, $title, $sel, 0);
            if ($href === '' && !$item['kids']) $add('menu-button-wide', sprintf(_LAYOUT_IS_NOWHERE, $word), $title, $sel, 0);
            elseif (($say = $link($href, null)) !== '') $add('link-45deg', $say, $title, $sel, 0);
        }
    }
    foreach ($dft['blocks'] as $bid => $set) {
        if (!is_array($set) || !empty($set['del'])) continue;
        $blk = array_replace(is_array($dft['bwas'][$bid] ?? null) ? $dft['bwas'][$bid] : [], $set);
        $word = getConst((string)($blk['title'] ?? $bid));
        $kind = (string)($blk['kind'] ?? '');
        if ($kind === 'html' && trim((string)($blk['content'] ?? '')) === '') $add('code-square', sprintf(_LAYOUT_IS_BLKNONE, $word), _LAYOUT_BLOCKS, 'blk:'.$bid, 0);
        if ($kind === 'rss' && preg_match('#^https?://.#i', (string)($blk['url'] ?? '')) !== 1) $add('rss', sprintf(_LAYOUT_IS_NORSS, $word), _LAYOUT_BLOCKS, 'blk:'.$bid, 0);
    }
    return $out;
}

# Whether the site answers an address of its own: a page of the draft, a module or a type with its operations, a path of the registry not gone, a file of the uploads
function getLayoutAnswer(array $lay): Closure {
    global $db, $conf;
    $route = new Route($db);
    $addrs = array_filter(array_map(static fn(array $v): string => (string)($v['addr'] ?? ''), $lay['pages']));
    $names = array_keys(($conf['modules'] ?? []) + ($conf['node']['types'] ?? []));
    return static function (string $path) use ($route, $addrs, $names): bool {
        $path = trim($path, '/');
        $base = trim((string)preg_replace('#^'.preg_quote(trim(HOME_PATH, '/'), '#').'(?:/|$)#', '', $path), '/');
        if (in_array($base, ['', 'index.php'], true) || in_array($base, $addrs, true)) return true;
        $seg = explode('/', $base);
        if ($seg[0] === 'uploads') return getLayoutWeight()($base) > 0;
        if (in_array($seg[0], $names, true) && count($seg) === 1) return true;
        if (in_array($seg[0], $names, true) && count($seg) <= 3 && $route->isRouteReserved($seg[0], $seg[1])) return true;
        $hit = $route->getRoute($base);
        return $hit !== [] && $hit['state'] !== 2;
    };
}

# The bytes of a picture of the uploads by its path below uploads/ or by its address on the site, 0 for anything else
function getLayoutWeight(): Closure {
    return static function (string $path): int {
        if (parse_url($path, PHP_URL_HOST) !== null) return 0;
        $path = ltrim((string)parse_url($path, PHP_URL_PATH), '/');
        $home = ltrim(HOME_PATH, '/');
        if ($home !== '' && str_starts_with($path, $home)) $path = substr($path, strlen($home));
        if (str_starts_with($path, 'uploads/')) $path = substr($path, 8);
        if (str_contains($path, '..') || preg_match('#^[a-z0-9][a-z0-9_-]*(?:/[A-Za-z0-9][A-Za-z0-9_.-]*)+$#D', $path) !== 1) return 0;
        $file = UPLOADS_DIR.'/'.$path;
        return is_file($file) ? intval(filesize($file)) : 0;
    };
}

# The ready pages the theme offers in its presets.json, the empty one of the screen first; a set keeps its place while one of its types is missing
function getLayoutPresets(): array {
    global $conf;
    $file = PUBLIC_DIR.'/templates/'.(string)($conf['theme'] ?? '').'/presets.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $out = ['empty' => ['title' => '_LAYOUT_RS_EMPTY', 'note' => '_LAYOUT_RS_EMPTY_I', 'icon' => 'file-earmark', 'sections' => []]];
    foreach (is_array($data) ? $data : [] as $key => $set) {
        if (is_array($set) && $key !== 'empty' && preg_match(Layout::FIELD, (string)$key) === 1 && is_array($set['sections'] ?? null)) $out[(string)$key] = $set;
    }
    return $out;
}

# A new page of the draft from a ready set: each section made from the defaults of its type with the values of the set, a type the library lacks or a choice it refuses passed over
function setLayoutPresetPage(array &$lay, array $types, array $set, string $title): int {
    $pkey = $lay['next']++;
    $show = ['who' => '0', 'lang' => '', 'from' => '', 'till' => '', 'anchor' => '', 'phone' => true, 'desk' => true];
    $secs = [];
    foreach ($set['sections'] as $one) {
        $type = is_array($one) ? (string)($one['type'] ?? '') : '';
        $kind = $types[$type] ?? null;
        if ($kind === null || !$kind['frag'] || ($kind['one'] && in_array($type, array_column($secs, 'type'), true))) continue;
        $make = array_replace(is_array($kind['desc']['make'] ?? null) ? $kind['desc']['make'] : [], is_array($one['make'] ?? null) ? $one['make'] : []);
        foreach (is_array($kind['desc']['fields'] ?? null) ? $kind['desc']['fields'] : [] as $fld) {
            if (($fld['type'] ?? '') === 'choice' && !array_key_exists((string)($make[$fld['key']] ?? ''), is_array($fld['options'] ?? null) ? $fld['options'] : [])) continue 2;
        }
        $secs[] = ['id' => $lay['next']++, 'type' => $type, 'on' => true] + getLayoutMake($make) + $show;
    }
    $lay['pages'][$pkey] = ['title' => $title, 'addr' => '', 'desc' => '', 'band' => !array_key_exists('band', $set) || !empty($set['band']), 'side' => '3', 'top' => '3',
        'og' => '', 'noindex' => false, 'who' => '0', 'sections' => $secs];
    $lay['pages'][$pkey]['addr'] = getLayoutAddr($lay, $title, $pkey);
    return $pkey;
}

# A copy of a whole page under a number of its own: its settings and its sections with ids of their own, a title and an address of its own; a slot stays with its page
function setLayoutPageCopy(array &$lay, int $pkey): array {
    $page = $lay['pages'][$pkey] ?? null;
    if (!is_array($page) || $pkey === $lay['notfound']) return [0, ''];
    $from = getLayoutPageTitle($lay, $pkey);
    $key = $lay['next']++;
    [$secs, $slot] = [[], false];
    foreach ($page['sections'] as $sec) {
        if (($sec['type'] ?? '') === Layout::SLOT) $slot = true;
        else $secs[] = array_replace($sec, ['id' => $lay['next']++]);
    }
    $title = sprintf(_LAYOUT_COPYOF, $from);
    $lay['pages'][$key] = array_replace($page, ['title' => $title, 'addr' => '', 'sections' => $secs]);
    $lay['pages'][$key]['addr'] = getLayoutAddr($lay, $title, $key);
    return [$key, sprintf(_LAYOUT_PAGECOPIED, $from).($slot ? ' '._LAYOUT_SLOTKEPT : '')];
}

# A page from a Markdown document: the first line "# " is its cover with the paragraph under it, each "## " with its lines a section "Text"; the answer is its key, title and parts
function setLayoutTextPage(array &$lay, array $types, string $src): array {
    [$title, $lead, $parts] = ['', '', []];
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $src)) as $line) {
        if ($title === '' && preg_match('/^#[ \t]+(\S.*)$/', $line, $hit) === 1) {
            $title = mb_substr(trim($hit[1]), 0, 255);
        } elseif (preg_match('/^##[ \t]+(\S.*)$/', $line, $hit) === 1) {
            $parts[] = ['title' => mb_substr(trim($hit[1]), 0, 255), 'body' => ''];
        } elseif ($parts) {
            $parts[count($parts) - 1]['body'] .= $line."\n";
        } elseif ($title !== '' && $lead === '' && trim($line) !== '') {
            $lead = trim($line);
        }
    }
    if ($title === '') return [0, '', 0];
    $list = [];
    if (!empty($types['hero']['frag'])) $list[] = ['hero', ['over' => '', 'title' => $title, 'accent' => '', 'title2' => '', 'text' => $lead, 'btn' => '', 'pic' => false]];
    foreach ($parts as $one) if (!empty($types['text']['frag'])) $list[] = ['text', ['title' => $one['title'], 'lead' => '', 'body' => trim($one['body'])]];
    $set = ['band' => $list === [] || $list[0][0] !== 'hero', 'sections' => array_map(static fn(array $v): array => ['type' => $v[0]], $list)];
    $key = setLayoutPresetPage($lay, $types, $set, $title);
    foreach ($lay['pages'][$key]['sections'] as $i => $sec) $lay['pages'][$key]['sections'][$i] = array_replace($sec, $list[$i][1] ?? []);
    $lay['pages'][$key]['desc'] = mb_substr($lead, 0, 500);
    return [$key, $title, count($parts)];
}

# The cards of the ready pages: a page from a text first, then the schema of a page as the bars of its sections, the title and the note of the set
function getLayoutReadyCards(array $types): array {
    $out = [['url' => '#', 'title' => _LAYOUT_FROMTEXT, 'subtitle' => _LAYOUT_FROMNOTE, 'title_attr' => _LAYOUT_FROMNOTE, 'thumb' => 'page',
        'icon_name' => 'file-earmark-text', 'minis' => ['hero', 'text', 'text'], 'link_attr' => 'data-sl-lay-from']];
    foreach (getLayoutPresets() as $key => $set) {
        $minis = [];
        foreach ($set['sections'] as $one) if (is_array($one) && isset($types[(string)($one['type'] ?? '')])) $minis[] = (string)$one['type'];
        $note = getConst((string)($set['note'] ?? ''));
        $out[] = ['url' => '#', 'title' => getConst((string)($set['title'] ?? $key)), 'subtitle' => $note, 'title_attr' => $note, 'thumb' => 'page',
            'icon_name' => (string)($set['icon'] ?? 'file-earmark'), 'minis' => $minis ?: ['empty'], 'link_attr' => 'data-sl-lay-ready="'.$key.'"'];
    }
    return $out;
}

# The sections kept in my sections, the oldest first, each its name, its time and the section without its id; an entry that does not read is passed over
function getLayoutMine(): array {
    $file = LAYOUT_DIR.'/'.LAYOUT_MINE;
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $out = [];
    foreach (is_array($data) ? $data : [] as $one) if (is_array($one) && is_array($one['sec'] ?? null) && is_string($one['sec']['type'] ?? null)) $out[] = $one;
    return $out;
}

# The cards of my sections in the library, out of band after a change; a type the library lacks or one taken on the page is shown off
function getLayoutMineHtml(array $types, array $taken, bool $oob = false): string {
    global $tpl;
    $cards = [];
    foreach (getLayoutMine() as $i => $one) {
        $type = $one['sec']['type'];
        $kind = $types[$type] ?? null;
        $off = $kind === null || !$kind['frag'] || ($kind['one'] && in_array($type, $taken, true));
        $name = (string)($one['name'] ?? $type);
        $sub = sprintf(_LAYOUT_MINEWHEN, date(_DATESTRING, intval($one['time'] ?? 0)));
        $cards[] = ['url' => '#', 'title' => $name, 'subtitle' => $sub, 'title_attr' => $name, 'thumb' => $type, 'icon_name' => $kind['icon'] ?? 'square',
            'is_off' => $off, 'words' => mb_strtolower($name), 'link_attr' => 'data-sl-lay-mine="'.$i.'"'.($off ? ' aria-disabled="true"' : ''), 'dial_title' => _ADM_ACTS,
            'dial' => [['href' => '#', 'title' => _LAYOUT_MINEDEL, 'icon_name' => 'x-lg', 'link_attr' => 'data-sl-lay-mine-del="'.$i.'"']]];
    }
    return $tpl->getHtmlPart('layout-mine', ['is_oob' => $oob, 'cards' => $cards, 'title' => _LAYOUT_MINE, 'num' => count($cards)]);
}

# The address of a block on the screen of blocks, where the feed of Node and the rest the builder does not edit are kept
function getLayoutBlockUrl(string $bid): string {
    global $afile;
    return $afile.'.php?'.http_build_query(['name' => 'blocks', 'op' => 'edit', 'id' => $bid]);
}

# The rows of the settings a theme part names in config/global.php: the name of the site and its logo, as the draft holds them
function getLayoutConfRows(array $dft): array {
    global $tpl, $conf;
    $name = (string)($dft['conf']['sitename'] ?? $conf['sitename'] ?? '');
    $logo = (string)($dft['conf']['site_logo'] ?? $conf['site_logo'] ?? '');
    $path = 'templates/'.(string)($conf['theme'] ?? '').'/images/logos/';
    $opts = '';
    foreach (scandir(PUBLIC_DIR.'/'.$path) ?: [] as $file) {
        if (!preg_match('/\.(gif|png|jpe?g|svg|webp)$/i', $file)) continue;
        $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $path.$file, 'label_text' => $file, 'is_selected' => $file === $logo]);
    }
    return [
        ['label_for' => 'f-lay-sitename', 'label_html' => _SITENAME, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'conf[sitename]', 'input_id' => 'f-lay-sitename',
            'value_attr' => $name, 'maxlength_num' => 255])],
        ['label_for' => 'f-lay-logo', 'label_html' => _LAYOUT_LOGO, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'conf[site_logo]', 'selectid' => 'f-lay-logo',
            'options_html' => $opts, 'imgtar' => 'f-lay-logo-img']).$tpl->getHtmlFrag('image-preview', ['src_attr' => HOME_PATH.$path.$logo, 'image_id' => 'f-lay-logo-img',
            'alt_text' => _LAYOUT_LOGO, 'title_text' => _LAYOUT_LOGO, 'is_popup' => false])],
    ];
}

# The state of the draft in the top bar: Undo and Redo in the capsule of tools, the chip and the publication at the edge; a part draws one for the screen, none both for a swap
function getLayoutChip(array $dft, bool $oob = false, string $part = ''): string {
    global $tpl, $conf;
    $names = ($part === 'steps') ? [] : getLayoutNames($dft);
    $dft = getLayoutStored($dft);
    $dirty = isLayoutDirty($dft);
    $view = getLayoutView($dft['layout']);
    $num = ($dirty && $part !== 'steps') ? count(getLayoutChanges($dft, Layout::getLayoutData($conf['layout'] ?? []), getLayoutTypes($view), $view->getThemePlaces())) : 0;
    return $tpl->getHtmlPart('layout-state', ['is_oob' => $oob, 'is_steps' => $part !== 'state', 'is_state' => $part !== 'steps', 'is_dirty' => $dirty,
        'state_text' => $dirty ? _LAYOUT_ISDRAFT.($num ? ' · '.$num : '') : _LAYOUT_ISLIVE,
        'state_title' => $num ? sprintf(_LAYOUT_DRAFTNUM, $num) : ($dirty ? _LAYOUT_DRAFTWAIT : ''), 'undo_text' => _LAYOUT_UNDO, 'redo_text' => _LAYOUT_REDO,
        'is_undo' => $dft['undo'] !== [], 'is_redo' => $dft['redo'] !== [],
        'rev' => $dft['rev'], 'live' => Layout::getLayoutHash($conf['layout'] ?? []), 'publish_text' => _LAYOUT_PUBLISH,
        'names' => (string)json_encode($names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

# The names the rings of the page in the frame show, by the pick each part of the page carries: the sections of every page, the blocks, the parts and the menu items
function getLayoutNames(array $dft): array {
    $lay = $dft['layout'];
    $view = getLayoutView($lay);
    $types = getLayoutTypes($view);
    $places = $view->getThemePlaces();
    $out = [];
    foreach ($lay['pages'] as $page) foreach ($page['sections'] as $sec) $out['sec:'.intval($sec['id'] ?? 0)] = getLayoutSecLabel($sec, $types);
    foreach (getLayoutBlocks($dft) as $bid => $blk) $out['blk:'.$bid] = _LAYOUT_BLOCK.' «'.getConst($blk['title']).'»';
    foreach ($places['parts'] as $pkey => $part) if (is_array($part)) $out['part:'.$pkey] = getConst((string)($part['title'] ?? $pkey));
    foreach (array_keys($places['menus']) as $mkey) {
        foreach (getLayoutFlat($lay['menus'][$mkey] ?? []) as $item) $out['menu:'.$mkey.':'.intval($item['id'] ?? 0)] = _LAYOUT_MITEM.' «'.(string)($item['title'] ?? '').'»';
    }
    return $out;
}

# The defaults of a description with its words resolved: a string with a leading underscore is a constant the editor reads when it makes a section or an item
function getLayoutMake(mixed $make): mixed {
    if (is_string($make)) return getConst($make);
    if (!is_array($make)) return $make;
    return array_map('getLayoutMake', $make);
}

# Whether a pick still names a part of the draft after a change
function isLayoutPicked(array $dft, string $sel): bool {
    $lay = $dft['layout'];
    $part = explode(':', $sel);
    return match ($part[0]) {
        'sec' => getLayoutSecPlace($lay, intval($part[1] ?? 0)) !== null,
        'page' => isset($lay['pages'][intval($part[1] ?? 0)]),
        'menu' => !isset($part[2]) || getLayoutItemPlace(is_array($lay['menus'][$part[1]] ?? null) ? $lay['menus'][$part[1]] : [], intval($part[2])) !== null,
        'blk' => isset(getLayoutBlocks($dft)[$part[1] ?? '']),
        'part' => true,
        default => false,
    };
}

# A free address for a page of the draft from its title, the number of the page added where another page holds it
function getLayoutAddr(array $lay, string $title, int $key): string {
    $addr = filterSlug($title) ?: 'page';
    foreach ($lay['pages'] as $one => $page) if ($one !== $key && (string)($page['addr'] ?? '') === $addr) return $addr.'-'.$key;
    return $addr;
}

# Answer a write of the screen: the tree, the inspector, the state and my sections swapped out of band, the notice and the reload of the frame raised as events
function setLayoutPane(array $dft, int $key, string $sel, string $tab, string $text, bool $undo, bool $insp = true, bool $warn = false): never {
    $evs = ['sl-layout-saved' => ['text' => $warn ? '' : $text, 'undo' => $undo ? 1 : 0, 'page' => $key, 'sel' => $sel, 'tab' => $tab]];
    if ($warn && $text !== '') $evs['sl-quick-note'] = $text;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('HX-Trigger: '.json_encode($evs));
    $types = getLayoutTypes(getLayoutView($dft['layout']));
    $mine = getLayoutMineHtml($types, array_column($dft['layout']['pages'][$key]['sections'] ?? [], 'type'), true);
    echo getLayoutTree($dft, $key, $tab, $sel, true).($insp ? getLayoutInsp($dft, $key, $sel, true) : '').getLayoutChip($dft, true).$mine;
    exit;
}

# Keep a changed draft as one step and write it; a draft that cannot be written is refused with its reason, a refused value is named after the write
function setLayoutKeep(array $dft, array $new, int $key, string $sel, string $tab, string $text, bool $undo, bool $insp = true, bool $warn = false): never {
    if (getLayoutState($new) == getLayoutState($dft)) setLayoutPane($dft, $key, $sel, $tab, $warn ? $text : '', false, $insp || $warn, $warn);
    $new['undo'] = array_slice(array_merge($dft['undo'], [getLayoutState(getLayoutStored($dft))]), -LAYOUT_STEPS);
    $new['redo'] = [];
    if (!setLayoutDraft($new)) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_NOWRITE, false, true, true);
    setLayoutPane($new, $key, $sel, $tab, $text, $undo, $insp || $warn, $warn);
}

function layout(): void {
    global $tpl, $afile, $adminvars;
    $dft = getLayoutDraft();
    $first = getLayoutStored($dft);
    $made = is_file(LAYOUT_DIR.'/'.Layout::DRAFT) || setLayoutDraft($first);
    [$dft['rev'], $dft['at']] = [$first['rev'], $first['at']];
    $lay = $dft['layout'];
    $key = (isset($dft['made']) && !getVar('req', 'page', 'num', 0)) ? $dft['made']['key'] : getLayoutPageKey($lay);
    $sel = getLayoutPick();
    if ($sel !== '' && !isLayoutPicked($dft, $sel)) $sel = '';
    $tab = getLayoutTab($sel);
    $view = getLayoutView($lay);
    $types = getLayoutTypes($view);
    $pages = [];
    foreach (array_keys($lay['pages']) as $one) {
        $pages[] = ['key' => $one, 'url' => getLayoutUrl(['page' => $one]), 'title' => getLayoutPageTitle($lay, $one), 'is_on' => $one === $key];
    }
    $held = [];
    foreach ($lay['pages'] as $page) foreach ($page['sections'] as $sec) if (($sec['type'] ?? '') === Layout::SLOT) $held[] = (string)($sec['module'] ?? '');
    $mods = [];
    foreach (getLayoutAddrs($lay)[_MODULES] as $path => $title) {
        $mod = substr($path, 1);
        if (!in_array($mod, $held, true)) $mods[] = ['url' => getLayoutUrl(['mod' => $mod]), 'title' => $title];
    }
    $sects = [];
    $taken = array_column($lay['pages'][$key]['sections'] ?? [], 'type');
    foreach ($types as $type => $one) {
        $off = !$one['frag'] || ($one['one'] && in_array($type, $taken, true));
        $why = !$one['frag'] ? _LAYOUT_NOFRAG : ($off ? sprintf(_LAYOUT_TAKEN, $one['title']) : $one['note']);
        $sects[$one['group']]['cards'][] = ['group' => $one['group'], 'words' => mb_strtolower($one['title'].' '.$one['note']), 'url' => '#', 'title' => $one['title'],
            'subtitle' => $why, 'title_attr' => $why, 'thumb' => $type, 'icon_name' => $one['icon'], 'is_off' => $off,
            'link_attr' => 'data-sl-lay-add="'.$type.'"'.($off ? ' aria-disabled="true"' : '')];
    }
    $places = $view->getThemePlaces();
    $parts = [];
    foreach ($places['parts'] as $pkey => $part) {
        if (!is_array($part)) continue;
        $parts[] = ['sel' => 'part:'.$pkey, 'id' => 'part-'.$pkey, 'icon' => (string)($part['icon'] ?? 'square'), 'title' => getConst((string)($part['title'] ?? $pkey))];
    }
    foreach ($places['menus'] as $mkey => $menu) {
        if (!is_array($menu)) continue;
        $parts[] = ['sel' => 'menu:'.$mkey, 'id' => 'menu-'.$mkey, 'icon' => 'menu-button-wide', 'title' => getConst((string)($menu['title'] ?? $mkey))];
    }
    $groups = [['key' => '', 'label' => _ALL, 'is_on' => true]];
    foreach (array_keys($sects) as $one) {
        $sects[$one]['label'] = getConst(LAYOUT_GROUP[$one]);
        $groups[] = ['key' => $one, 'label' => $sects[$one]['label'], 'is_on' => false];
    }
    $mod = (string)($dft['made']['mod'] ?? '');
    $frame = HOME_PATH.'index.php?'.http_build_query(['name' => 'page-'.$key, 'draft' => Layout::getDraftToken($key, time() + 7200, $dft['link']), 'edit' => 1]
        + (($mod !== '') ? ['mod' => $mod] : []));
    $words = ['grip' => _LAYOUT_DRAG, 'up' => _LAYOUT_UP, 'down' => _LAYOUT_DOWN, 'dup' => _LAYOUT_DUP, 'link' => _LAYOUT_SECLINK, 'hide' => _LAYOUT_HIDESEC,
        'del' => _DELETE, 'add' => _LAYOUT_ADDSEC];
    setHead();
    $adminvars['is_full'] = true;
    if (!$made) echo $tpl->getHtmlFrag('alert', ['text' => _LAYOUT_NOWRITE, 'meta' => '', 'type' => 'error', 'is_warn' => true]);
    echo $tpl->getHtmlPart('layout-screen', [
        'act_url' => getLayoutUrl(['op' => 'act']), 'pick_url' => getLayoutUrl(['op' => 'pick']), 'tree_url' => getLayoutUrl(['op' => 'tree']), 'token' => getSiteToken('layout'),
        'page' => $key, 'sel' => $sel, 'tab' => $tab, 'panel_url' => HOME_PATH.$afile.'.php', 'panel_text' => _LAYOUT_PANEL, 'panel_title' => _ADMINMENU,
        'title_text' => _LAYOUT_BUILDER, 'page_label' => _PAGE, 'pages' => $pages, 'pages_group' => _LAYOUT_PAGES, 'mods' => $mods, 'mods_group' => _MODULES,
        'new_text' => $lay['shipped'] ? _LAYOUT_READYPICK : _LAYOUT_NEWPAGE, 'width_label' => _LAYOUT_WIDTH,
        'devices' => [['key' => 'desk', 'icon' => 'display', 'label' => _LAYOUT_DESK, 'is_on' => true], ['key' => 'tablet', 'icon' => 'tablet', 'label' => _LAYOUT_TABLET],
            ['key' => 'phone', 'icon' => 'phone', 'label' => _LAYOUT_PHONE]],
        'tools_label' => _LAYOUT_TOOLS, 'site_url' => HOME_PATH, 'site_text' => _LAYOUT_OPENSITE, 'cmds_text' => _LAYOUT_CMDS, 'help_url' => getLayoutUrl(['op' => 'info']),
        'help_text' => _MANUAL, 'expand_text' => _EEXPAND, 'restore_text' => _ERESTORE, 'sets_text' => _LAYOUT_SETS, 'help_label' => _LAYOUT_HELPSETS,
        'steps_html' => getLayoutChip($dft, false, 'steps'), 'state_html' => getLayoutChip($dft, false, 'state'),
        'tree_label' => _LAYOUT_TREE, 'tree_html' => getLayoutTree($dft, $key, $tab, $sel), 'stage_label' => _LAYOUT_STAGE, 'frame_url' => $frame, 'frame_title' => _LAYOUT_FRAME,
        'insp_html' => getLayoutInsp($dft, $key, $sel), 'lib_title' => _LAYOUT_ADDSEC, 'lib_groups' => $groups, 'lib_groups_label' => _LAYOUT_GROUPS, 'lib_find' => _LAYOUT_FINDSEC,
        'lib_after' => _LAYOUT_AFTER, 'lib_start' => _LAYOUT_ATSTART, 'lib_note' => _LAYOUT_LIBNOTE, 'lib_sects' => array_values($sects),
        'none_text' => _NONE_FOUND, 'close_text' => _CLOSE,
        'sets_title' => _LAYOUT_SETS, 'where_title' => _LAYOUT_WHERE, 'links' => getLayoutLinks(), 'pal_title' => _LAYOUT_CMDS, 'pal_find' => _LAYOUT_PALFIND,
        'pal_acts' => getLayoutCommands(), 'pal_acts_title' => _ADM_ACTS, 'pal_pages_title' => _LAYOUT_PAGES, 'pal_secs_title' => _LAYOUT_SECS,
        'pal_add_title' => _LAYOUT_ADDSEC, 'pal_add' => _ADD, 'pal_parts_title' => _LAYOUT_AT_ALL, 'pal_parts' => $parts, 'pal_pages' => $pages, 'undo_text' => _UNDO,
        'ed' => bin2hex(random_bytes(6)), 'story_url' => getLayoutUrl(['op' => 'story']), 'story_title' => _LAYOUT_STORY,
        'story_sub' => _LAYOUT_STORYSUB, 'mine_html' => getLayoutMineHtml($types, $taken), 'mine_label' => _LAYOUT_MINE, 'recent_label' => _LAYOUT_RECENT,
        'ready_title' => _LAYOUT_NEWPAGE, 'ready_sub' => $lay['shipped'] ? _LAYOUT_READYSHIP : _LAYOUT_READYSUB, 'ready_cards' => getLayoutReadyCards($types),
        'checks_url' => getLayoutUrl(['op' => 'checks']), 'checks_text' => _LAYOUT_CHECKS, 'dest_url' => getLayoutUrl(['op' => 'dest']), 'dest_title' => _LAYOUT_TOPAGE,
        'guest_text' => _LAYOUT_GUEST, 'guest_title' => _LAYOUT_GUESTTIP, 'preview_note' => _LAYOUT_PREVNOTE, 'edit_text' => _LAYOUT_BACKEDIT,
        'edit_url' => getLayoutUrl(['op' => 'edit']), 'edit_title' => _EDIT, 'source_url' => getLayoutUrl(['op' => 'source']), 'file_title' => _LAYOUT_FILETAB,
        'link_tip' => _LAYOUT_LINKTIP, 'link_title' => _LAYOUT_LINK, 'link_sub' => _LAYOUT_LINKSUB, 'term_label' => _LAYOUT_LINKTERM, 'link_make' => _LAYOUT_LINKMAKE,
        'terms' => array_map(static fn(int $v): array => ['value' => $v, 'label' => sprintf(getPluralForm($v, _LAYOUT_DAYS), $v), 'is_on' => $v === LAYOUT_TERMS[1]], LAYOUT_TERMS),
        'copy_text' => _COPY, 'copied_text' => _COPIED, 'cancel_text' => _CANCEL, 'save_url' => getLayoutUrl(['op' => 'save']), 'mod' => $mod,
        'export_url' => getLayoutUrl(['op' => 'export']), 'export_text' => _LAYOUT_EXPORT, 'export_tip' => _LAYOUT_EXPTIP, 'import_text' => _LAYOUT_IMPORT,
        'import_tip' => _LAYOUT_IMPTIP,
        'words' => (string)json_encode($words, JSON_UNESCAPED_UNICODE),
        'link_note_html' => $tpl->getHtmlFrag('alert', ['text' => _LAYOUT_LINKNOTE, 'meta' => '', 'type' => 'info', 'is_warn' => false]),
    ]).getAdminIconWindow();
    setFoot();
}

# The commands of the palette of the screen, each with its icon and its keys
function getLayoutCommands(): array {
    $out = [
        ['undo', 'arrow-counterclockwise', _LAYOUT_UNDO, ['Ctrl', 'Z']],
        ['redo', 'arrow-clockwise', _LAYOUT_REDO, ['Ctrl', 'Shift', 'Z']],
        ['publish', 'cloud-arrow-up', _LAYOUT_PUBLISH, []],
        ['checks', 'clipboard-check', _LAYOUT_CHECKS, []],
        ['story', 'clock-history', _LAYOUT_STORY, []],
        ['guest', 'person', _LAYOUT_GUEST, []],
        ['link', 'link-45deg', _LAYOUT_LINKTIP, []],
        ['preview', 'arrows-fullscreen', _LAYOUT_PREVIEW, ['Ctrl', 'Alt', 'P']],
        ['lib', 'plus-square', _LAYOUT_ADDSEC, []],
        ['page-add', 'file-earmark-plus', _LAYOUT_NEWPAGE, []],
        ['blk-add', 'bricks', _ADDNEWBLOCK, []],
        ['tab-page', 'file-earmark-richtext', _PAGE, []],
        ['tab-menu', 'menu-button-wide', _LAYOUT_MENUS, []],
        ['tab-theme', 'palette', _LAYOUT_THEME, []],
        ['dev-desk', 'display', _LAYOUT_WIDTH.': '._LAYOUT_DESK, []],
        ['dev-tablet', 'tablet', _LAYOUT_WIDTH.': '._LAYOUT_TABLET, []],
        ['dev-phone', 'phone', _LAYOUT_WIDTH.': '._LAYOUT_PHONE, []],
        ['sets', 'gear', _LAYOUT_SETS, []],
        ['site', 'box-arrow-up-right', _LAYOUT_OPENSITE, []],
        ['help', 'question-circle', _MANUAL, []],
    ];
    return array_map(static fn(array $v): array => ['cmd' => $v[0], 'icon' => $v[1], 'label' => $v[2], 'keys' => $v[3]], $out);
}

# The screens of the panel that keep what the builder does not: each with its icon, its name and what it holds for the builder
function getLayoutLinks(): array {
    global $afile;
    $list = [
        ['uploads', 'cloud-arrow-up', _UPLOADS, _LAYOUT_W_UPL],
        ['editor', 'pencil-square', _EDITOR, _LAYOUT_W_EDIT],
        ['config', 'sliders', _PREFERENCES, _LAYOUT_W_CONF],
        ['blocks', 'bricks', _BLOCKS, _LAYOUT_W_BLK],
        ['modules', 'puzzle', _MODULES, _LAYOUT_W_MOD],
    ];
    return array_map(static fn(array $v): array => ['href' => HOME_PATH.$afile.'.php?name='.$v[0], 'icon' => $v[1], 'title' => $v[2], 'text' => $v[3]], $list);
}

function tree(): void {
    $dft = getLayoutDraft();
    $key = getLayoutPageKey($dft['layout']);
    $sel = getLayoutPick();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo getLayoutTree($dft, $key, getLayoutTab($sel), $sel);
    exit;
}

function pick(): void {
    $dft = getLayoutDraft();
    $key = getLayoutPageKey($dft['layout']);
    $sel = getLayoutPick();
    if ($sel !== '' && !isLayoutPicked($dft, $sel)) $sel = '';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo getLayoutInsp($dft, $key, $sel);
    exit;
}

# Take the posted settings of config/global.php a theme part names into the draft; a value equal to the site leaves the draft
function setLayoutConf(array &$new, array $sets, array &$bad): void {
    global $conf;
    if (array_key_exists('sitename', $sets)) {
        $site = is_string($sets['sitename']) ? trim($sets['sitename']) : '';
        if ($site === '' || mb_strlen($site) > 255 || preg_match('/[\x00-\x1F\x7F]/', $site)) $bad[] = _SITENAME;
        elseif ($site === (string)($conf['sitename'] ?? '')) unset($new['conf']['sitename']);
        else $new['conf']['sitename'] = $site;
    }
    if (!array_key_exists('site_logo', $sets)) return;
    $logo = basename(is_string($sets['site_logo']) ? $sets['site_logo'] : '');
    if ($logo === '' || !is_file(PUBLIC_DIR.'/templates/'.(string)($conf['theme'] ?? '').'/images/logos/'.$logo)) $bad[] = _LAYOUT_LOGO;
    elseif ($logo === (string)($conf['site_logo'] ?? '')) unset($new['conf']['site_logo']);
    else $new['conf']['site_logo'] = $logo;
}

# Take the posted values of the items of a list into it; an item the post does not carry keeps its values
function setLayoutItems(array $desc, mixed $list, array $rows, array &$bad): array {
    $list = is_array($list) ? array_values($list) : [];
    $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
    foreach ($list as $i => $item) if (is_array($rows[$i] ?? null) && is_array($item)) setLayoutValues($fields, $rows[$i], $list[$i], $bad, 'item['.$i.']');
    return $list;
}

function save(): void {
    $dft = getLayoutDraft();
    $key = getLayoutPageKey($dft['layout']);
    $sel = getLayoutPick();
    $tab = getLayoutTab($sel);
    if (!checkAdminPost('layout')) setLayoutPane($dft, $key, $sel, $tab, _TOKENMISS, false, true, true);
    if (($busy = checkLayoutEditor($dft)) !== '') setLayoutPane($dft, $key, $sel, $tab, $busy, false, true, true);
    $post = getVar('post', 'field[]', '', []);
    $post = is_array($post) ? $post : [];
    $rows = getVar('post', 'item[]');
    $rows = is_array($rows) ? $rows : [];
    $new = $dft;
    $lay = $new['layout'];
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $part = explode(':', $sel);
    $bad = [];
    if ($part[0] === 'sec' && ($at = getLayoutSecPlace($lay, intval($part[1]))) !== null) {
        $sec = $lay['pages'][$at[0]]['sections'][$at[1]];
        $one = getLayoutTypes($view)[(string)($sec['type'] ?? '')] ?? null;
        if ($one !== null) {
            $desc = $one['desc'];
            setLayoutValues(array_merge(is_array($desc['fields'] ?? null) ? $desc['fields'] : [], getLayoutShowFields()), $post, $sec, $bad);
            if (is_array($desc['items'] ?? null)) $sec['items'] = setLayoutItems($desc['items'], $sec['items'] ?? [], $rows, $bad);
            $lay['pages'][$at[0]]['sections'][$at[1]] = $sec;
        }
    } elseif ($part[0] === 'part' && is_array($places['parts'][$part[1]] ?? null)) {
        $one = $places['parts'][$part[1]];
        setLayoutValues(is_array($one['fields'] ?? null) ? $one['fields'] : [], $post, $lay['theme'], $bad);
        $list = is_array($one['items'] ?? null) ? $one['items'] : [];
        if (preg_match(Layout::FIELD, (string)($list['key'] ?? '')) === 1) $lay['theme'][$list['key']] = setLayoutItems($list, $lay['theme'][$list['key']] ?? [], $rows, $bad);
        $sets = getVar('post', 'conf[]');
        if (is_array($sets) && is_array($one['conf'] ?? null)) setLayoutConf($new, $sets, $bad);
    } elseif ($part[0] === 'menu' && isset($part[2]) && is_array($places['menus'][$part[1]] ?? null)) {
        $list = is_array($lay['menus'][$part[1]] ?? null) ? $lay['menus'][$part[1]] : [];
        if (($at = getLayoutItemPlace($list, intval($part[2]))) !== null) {
            $fields = getLayoutItemFields($places['menus'][$part[1]]);
            if (isset($at[1])) setLayoutValues($fields, $post, $list[$at[0]]['kids'][$at[1]], $bad);
            else setLayoutValues($fields, $post, $list[$at[0]], $bad);
            $lay['menus'][$part[1]] = $list;
        }
    } elseif ($part[0] === 'page' && isset($lay['pages'][intval($part[1])])) {
        $pkey = intval($part[1]);
        $page = $lay['pages'][$pkey];
        $page['noindex'] = empty($page['noindex']) ? '0' : '1';
        setLayoutValues(getLayoutPageFields($lay, $pkey), $post, $page, $bad);
        $page['noindex'] = $page['noindex'] === '1';
        if (array_key_exists('addr', $post)) $page['addr'] = getLayoutAddr($lay, (string)(($page['addr'] ?? '') ?: ($page['title'] ?? '')), $pkey);
        $lay['pages'][$pkey] = $page;
    } elseif ($part[0] === 'blk' && ($blk = getLayoutBlocks($dft)[$part[1]] ?? null) !== null) {
        $blks = getLayoutBlocks($dft);
        $vals = $blk;
        setLayoutValues(getLayoutBlockFields($blk, $places['zones']), $post, $vals, $bad);
        if (is_array($post['which'] ?? null)) {
            $allow = getLayoutWhere($blk['which']);
            $vals['which'] = array_values(array_filter(array_map('strval', $post['which']), static fn(string $v): bool => $v !== '' && isset($allow[$v])));
        }
        if ($blk['kind'] !== 'sys' && $vals['kind'] !== 'file') $vals['bfile'] = '';
        if ($blk['kind'] !== 'sys' && $vals['kind'] !== 'rss') $vals['url'] = '';
        if ($vals['bpos'] !== $blk['bpos']) $vals['weight'] = 1 + count(array_filter($blks, static fn(array $v): bool => $v['bpos'] === $vals['bpos'] && $v['id'] !== $blk['id']));
        setLayoutBlock($new, $blk, array_diff_key($vals, ['id' => true, 'new' => true, 'key' => true]));
    }
    $new['layout'] = $lay;
    $text = $bad ? sprintf(_LAYOUT_BADVAL, implode(', ', array_unique($bad))) : '';
    $sent = array_filter($_FILES, static fn(mixed $v): bool => is_array($v) && intval($v['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) !== [];
    setLayoutKeep($dft, $new, $key, $sel, $tab, $text, false, $sent || getVar('post', 'redraw', 'num', 0) === 1, $bad !== []);
}

# Change the sections of the draft by one act of the tree, the foot or the library, a kept section of my sections given as $from; the answer is the pick and the notice
function setLayoutSecAct(array &$lay, array $types, string $act, int $sid, string $arg, int $key, string $sel, ?array $from = null): array {
    $show = ['who' => '0', 'lang' => '', 'from' => '', 'till' => '', 'anchor' => '', 'phone' => true, 'desk' => true];
    if ($act === 'sec-add') {
        $one = $types[$arg] ?? null;
        $list = $lay['pages'][$key]['sections'] ?? null;
        if ($one === null || !is_array($list) || !$one['frag']) return [$sel, '', false, ''];
        if ($one['one'] && in_array($arg, array_column($list, 'type'), true)) return [$sel, '', false, sprintf(_LAYOUT_TAKEN, $one['title'])];
        $make = ($from !== null) ? $from : (is_array($one['desc']['make'] ?? null) ? getLayoutMake($one['desc']['make']) : []);
        $sec = ['id' => $lay['next']++, 'type' => $arg, 'on' => true] + $make + $show;
        $here = str_starts_with($sel, 'sec:') ? getLayoutSecPlace($lay, intval(substr($sel, 4))) : null;
        array_splice($list, ($here !== null && $here[0] === $key) ? $here[1] + 1 : count($list), 0, [$sec]);
        $lay['pages'][$key]['sections'] = $list;
        return ['sec:'.$sec['id'], sprintf(_LAYOUT_ADDED, $one['title']), false, ''];
    }
    if ($act === 'sec-order') {
        $list = $lay['pages'][$key]['sections'] ?? [];
        $byid = array_column($list, null, 'id');
        $ids = array_map('intval', explode('-', $arg));
        if (count($ids) === count($list) && array_diff($ids, array_keys($byid)) === []) $lay['pages'][$key]['sections'] = array_map(static fn(int $v): array => $byid[$v], $ids);
        return [$sel, '', false, ''];
    }
    if (($at = getLayoutSecPlace($lay, $sid)) === null) return [$sel, '', false, ''];
    [$pkey, $pos] = $at;
    $list = $lay['pages'][$pkey]['sections'];
    $sec = $list[$pos];
    $name = $types[(string)($sec['type'] ?? '')]['title'] ?? (string)($sec['type'] ?? '');
    $out = [$sel, '', false, ''];
    if ($act === 'sec-del') {
        array_splice($list, $pos, 1);
        $out = [($sel === 'sec:'.$sid) ? '' : $sel, sprintf(_LAYOUT_SECDEL, $name), true, ''];
    } elseif ($act === 'sec-toggle') {
        $list[$pos]['on'] = empty($sec['on']);
        $out = [$sel, sprintf(empty($sec['on']) ? _LAYOUT_SECSHOWN : _LAYOUT_SECHID, $name), !empty($sec['on']), ''];
    } elseif ($act === 'sec-dup' && ($sec['type'] ?? '') !== Layout::SLOT) {
        $copy = array_replace($sec, ['id' => $lay['next']++]);
        array_splice($list, $pos + 1, 0, [$copy]);
        $out = ['sec:'.$copy['id'], sprintf(_LAYOUT_SECDUP, $name), false, ''];
    } elseif ($act === 'sec-up' || $act === 'sec-down') {
        $to = $pos + (($act === 'sec-up') ? -1 : 1);
        if (isset($list[$to])) [$list[$pos], $list[$to]] = [$list[$to], $list[$pos]];
    } elseif ($act === 'sec-move') {
        [$to, $after] = array_map('intval', explode('-', $arg) + [0, 0]);
        $rest = array_values(array_filter($list, static fn(array $v): bool => intval($v['id'] ?? 0) !== $sid));
        $at = array_search($to, array_map('intval', array_column($rest, 'id')), true);
        if ($at === false) return $out;
        array_splice($rest, $at + ($after ? 1 : 0), 0, [$sec]);
        $list = $rest;
    } elseif ($act === 'sec-copy') {
        $to = intval($arg);
        if ($to === $pkey || !isset($lay['pages'][$to]) || !empty($types[(string)($sec['type'] ?? '')]['one'])) return $out;
        $lay['pages'][$to]['sections'][] = array_replace($sec, ['id' => $lay['next']++]);
        $out = [$sel, sprintf(_LAYOUT_SECCOPY, $name, getLayoutPageTitle($lay, $to)), true, ''];
    } elseif ($act === 'look-paste') {
        $at = getLayoutSecPlace($lay, intval($arg));
        $src = ($at !== null) ? $lay['pages'][$at[0]]['sections'][$at[1]] : null;
        $keys = getLayoutLookKeys($types[(string)($sec['type'] ?? '')]['desc'] ?? []);
        if ($src === null || ($src['type'] ?? '') !== ($sec['type'] ?? '') || intval($src['id']) === $sid || !$keys) return [$sel, '', false, _LAYOUT_LOOKGONE];
        foreach ($keys as $one) if (array_key_exists($one, $src)) $list[$pos][$one] = $src[$one];
        $out = [$sel, sprintf(_LAYOUT_LOOKDONE, $name), true, ''];
    }
    $lay['pages'][$pkey]['sections'] = $list;
    return $out;
}

# One section of a publication back into the draft: in its place while its page holds it, else at its old place in the order; the answer is the pick and a refusal
function setLayoutSecBack(array &$lay, array $types, array $old, int $pkey, int $sid): array {
    $from = Layout::getLayoutData(is_array($old['layout'] ?? null) ? $old['layout'] : []);
    $list = $from['pages'][$pkey]['sections'] ?? [];
    $at = array_search($sid, array_map('intval', array_column($list, 'id')), true);
    if ($at === false) return ['', _LAYOUT_STORYGONE];
    if (!isset($lay['pages'][$pkey])) return ['', sprintf(_LAYOUT_BACKPAGE, getLayoutPageTitle($from, $pkey))];
    $sec = $list[$at];
    $type = (string)($sec['type'] ?? '');
    $now = $lay['pages'][$pkey]['sections'];
    $here = array_search($sid, array_map('intval', array_column($now, 'id')), true);
    if ($here === false && !empty($types[$type]['one']) && in_array($type, array_column($now, 'type'), true)) return ['', sprintf(_LAYOUT_TAKEN, $types[$type]['title'])];
    if ($here !== false) $now[$here] = $sec;
    else array_splice($now, min($at, count($now)), 0, [$sec]);
    $lay['pages'][$pkey]['sections'] = $now;
    return ['sec:'.$sid, ''];
}

# Change the items of the picked section or theme part: add one at the end, delete one, move one up or down
function setLayoutItemAct(array &$lay, array $types, array $places, string $act, string $sel, int $num): array {
    $part = explode(':', $sel);
    $desc = null;
    $path = [];
    if ($part[0] === 'sec' && ($own = getLayoutSecPlace($lay, intval($part[1] ?? 0))) !== null) {
        $desc = $types[(string)($lay['pages'][$own[0]]['sections'][$own[1]]['type'] ?? '')]['desc']['items'] ?? null;
        $path = ['pages', $own[0], 'sections', $own[1], 'items'];
    } elseif ($part[0] === 'part' && is_array($places['parts'][$part[1] ?? '']['items'] ?? null)) {
        $desc = $places['parts'][$part[1]]['items'];
        if (preg_match(Layout::FIELD, (string)($desc['key'] ?? '')) === 1) $path = ['theme', $desc['key']];
    }
    if (!is_array($desc) || $path === []) return ['', false];
    $box = &$lay;
    foreach ($path as $step) $box = &$box[$step];
    $list = is_array($box) ? array_values($box) : [];
    $out = ['', false];
    if ($act === 'item-add' && count($list) < intval($desc['max'] ?? 0)) {
        $list[] = is_array($desc['make'] ?? null) ? getLayoutMake($desc['make']) : [];
        $out = [_LAYOUT_ITEMADD, false];
    } elseif ($act === 'item-del' && isset($list[$num])) {
        array_splice($list, $num, 1);
        $out = [_LAYOUT_ITEMDEL, true];
    } elseif (($act === 'item-up' || $act === 'item-down') && isset($list[$num])) {
        $to = $num + (($act === 'item-up') ? -1 : 1);
        if (isset($list[$to])) [$list[$num], $list[$to]] = [$list[$to], $list[$num]];
    }
    $box = $list;
    unset($box);
    return $out;
}

# Change a menu of the draft: delete an item, nest it into the item above, bring it a level up, or order the items of one level
function setLayoutMenuAct(array &$lay, array $places, string $act, string $sel, int $iid, string $arg): array {
    $mkey = explode(':', $sel)[1] ?? '';
    $menu = $places['menus'][$mkey] ?? null;
    if (!is_array($menu)) return [$sel, '', false];
    $list = array_values(is_array($lay['menus'][$mkey] ?? null) ? $lay['menus'][$mkey] : []);
    $out = [$sel, '', false];
    if ($act === 'menu-order') {
        $own = $iid ? getLayoutItemPlace($list, $iid) : null;
        $kin = $iid ? (($own !== null && !isset($own[1])) ? $list[$own[0]]['kids'] : null) : $list;
        if (!is_array($kin)) return $out;
        $byid = array_column($kin, null, 'id');
        $ids = array_map('intval', explode('-', $arg));
        if (count($ids) !== count($kin) || array_diff($ids, array_keys($byid)) !== []) return $out;
        $kin = array_map(static fn(int $v): array => $byid[$v], $ids);
        if ($iid) $list[$own[0]]['kids'] = $kin;
        else $list = $kin;
    } elseif (($own = getLayoutItemPlace($list, $iid)) !== null) {
        if ($act === 'menu-del') {
            if (isset($own[1])) array_splice($list[$own[0]]['kids'], $own[1], 1);
            else array_splice($list, $own[0], 1);
            $out = [($sel === 'menu:'.$mkey.':'.$iid) ? 'menu:'.$mkey : $sel, _LAYOUT_ITEMDEL, true];
        } elseif ($act === 'menu-in' && !isset($own[1]) && $own[0] > 0 && intval($menu['depth'] ?? 1) > 1 && empty($list[$own[0]]['kids'])) {
            $item = $list[$own[0]];
            array_splice($list, $own[0], 1);
            $list[$own[0] - 1]['kids'][] = $item;
        } elseif ($act === 'menu-out' && isset($own[1])) {
            $item = $list[$own[0]]['kids'][$own[1]];
            array_splice($list[$own[0]]['kids'], $own[1], 1);
            array_splice($list, $own[0] + 1, 0, [$item]);
        }
    }
    $lay['menus'][$mkey] = $list;
    return $out;
}

# Change the blocks of the draft: a new one, a deletion, a switch, or the order of one zone; the answer is the pick and the words of the notice
function setLayoutBlockAct(array &$new, array $places, string $act, string $bid, string $arg, string $sel): array {
    $blks = getLayoutBlocks($new);
    if ($act === 'blk-add') {
        $nid = 'n'.(++$new['bnext']);
        $zone = isset($places['zones']['r']) ? 'r' : (string)(array_key_first($places['zones']) ?? 'r');
        $weight = 1 + count(array_filter($blks, static fn(array $v): bool => $v['bpos'] === $zone));
        $new['blocks'][$nid] = ['id' => $nid, 'key' => '', 'title' => _LAYOUT_NEWBLK, 'kind' => 'html', 'bfile' => '', 'content' => '', 'url' => '', 'bpos' => $zone,
            'weight' => $weight, 'status' => false, 'view' => '0', 'lang' => '', 'days' => 0, 'action' => 'd', 'which' => ['all'], 'new' => true];
        return ['blk:'.$nid, _LAYOUT_BLKADD, false];
    }
    if ($act === 'blk-order') {
        if (isset($places['zones'][$bid])) setLayoutZone($new, $blks, $bid, explode('-', $arg));
        return [$sel, '', false];
    }
    $blk = $blks[$bid] ?? null;
    if ($blk === null) return [$sel, '', false];
    if ($act === 'blk-toggle') {
        setLayoutBlock($new, $blk, ['status' => !$blk['status']]);
        return [$sel, sprintf($blk['status'] ? _LAYOUT_BLKHID : _LAYOUT_BLKSHOWN, getConst($blk['title'])), $blk['status']];
    }
    if ($act !== 'blk-del' || $blk['kind'] === 'sys') return [$sel, '', false];
    if ($blk['new']) unset($new['blocks'][$bid]);
    if (!$blk['new'] && !isset($new['blocks'][$bid])) $new['bwas'][$bid] = array_diff_key($blk, ['new' => true]);
    if (!$blk['new']) $new['blocks'][$bid] = ['del' => true];
    return [($sel === 'blk:'.$bid) ? '' : $sel, sprintf(_LAYOUT_BLKDEL, getConst($blk['title'])), true];
}

function act(): void {
    $dft = getLayoutDraft();
    $key = getLayoutPageKey($dft['layout']);
    $sel = getLayoutPick();
    $tab = getLayoutTab($sel);
    if (!checkAdminPost('layout')) setLayoutPane($dft, $key, $sel, $tab, _TOKENMISS, false, true, true);
    if (($busy = checkLayoutEditor($dft)) !== '') setLayoutPane($dft, $key, $sel, $tab, $busy, false, true, true);
    $act = getVar('post', 'act', 'var', '');
    $bid = getVar('post', 'id', 'var', '');
    $arg = getVar('post', 'arg', 'var', '');
    $was = getLayoutStored($dft);
    if ($act === 'undo' || $act === 'redo') setLayoutBack($was, $key, $sel, $tab, $act === 'undo');
    if ($act === 'publish') setLayoutPublish($was, $key, $sel, $tab);
    if ($act === 'story-back') setLayoutStoryBack($was, intval($bid), $key, $sel, $tab);
    if ($act === 'discard') setLayoutDiscard($was, $key, $sel, $tab);
    if ($act === 'import') setLayoutImport($was, $key, $sel, $tab);
    if ($act === 'sec-mine' || $act === 'mine-del') setLayoutMineAct($dft, $act, intval($bid), $key, $sel, $tab);
    if ($act === 'link') setLayoutLink($dft, $key, $sel, $tab, intval($arg));
    $new = $dft;
    $lay = $new['layout'];
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $types = getLayoutTypes($view);
    [$text, $undo, $warn] = ['', false, ''];
    if ($act === 'sec-back') {
        [$num, $pkey, $sid] = array_map('intval', explode('-', $arg) + [0, 0, 0]);
        $old = getLayoutStory()[$num] ?? null;
        [$pick, $warn] = ($old === null) ? ['', _LAYOUT_STORYGONE] : setLayoutSecBack($lay, $types, $old, $pkey, $sid);
        if ($warn === '') [$key, $sel, $tab, $undo, $text] = [$pkey, $pick, 'page', true, sprintf(_LAYOUT_SECBACK, date(_TIMESTRING, intval($old['time'] ?? 0)))];
    } elseif ($act === 'mine-add') {
        $one = getLayoutMine()[intval($arg)] ?? null;
        $warn = ($one === null) ? _LAYOUT_MINEGONE : '';
        if ($one !== null) [$sel, $text, $undo, $warn] = setLayoutSecAct($lay, $types, 'sec-add', 0, $one['sec']['type'], $key, $sel, array_diff_key($one['sec'], ['id' => true]));
        $tab = 'page';
    } elseif ($act === 'field-back') {
        $new['layout'] = $lay;
        $text = setLayoutFieldBack($new, $places, $sel, $arg) ? _LAYOUT_LIVEDONE : '';
        [$lay, $undo] = [$new['layout'], $text !== ''];
    } elseif (str_starts_with($act, 'sec-') || $act === 'look-paste') {
        [$sel, $text, $undo, $warn] = setLayoutSecAct($lay, $types, $act, intval($bid), $arg, $key, $sel);
        $tab = 'page';
    } elseif (str_starts_with($act, 'item-')) {
        [$text, $undo] = setLayoutItemAct($lay, $types, $places, $act, $sel, intval($arg));
    } elseif (str_starts_with($act, 'menu-') && $act !== 'menu-add') {
        [$sel, $text, $undo] = setLayoutMenuAct($lay, $places, $act, $sel, intval($bid), $arg);
    } elseif ($act === 'menu-add' && is_array($places['menus'][$arg] ?? null)) {
        $iid = $lay['next']++;
        $lay['menus'][$arg] = array_values(is_array($lay['menus'][$arg] ?? null) ? $lay['menus'][$arg] : []);
        $lay['menus'][$arg][] = ['id' => $iid, 'title' => _LAYOUT_NEWITEM, 'href' => '', 'icon' => '', 'blank' => false, 'who' => '0', 'kids' => []];
        [$sel, $tab, $text] = ['menu:'.$arg.':'.$iid, 'menu', _LAYOUT_ITEMADD];
    } elseif ($act === 'page-add') {
        $sets = getLayoutPresets();
        $set = $sets[$arg] ?? $sets['empty'];
        $title = (isset($sets[$arg]) && $arg !== 'empty') ? getConst((string)$set['title']) : _LAYOUT_NEWPAGE;
        $pkey = setLayoutPresetPage($lay, $types, $set, $title);
        $num = count($lay['pages'][$pkey]['sections']);
        [$key, $sel, $tab, $undo] = [$pkey, 'page:'.$pkey, 'page', true];
        $text = $num ? sprintf(_LAYOUT_PAGEMADE, $title, $num) : sprintf(_LAYOUT_PAGENEW, $title);
    } elseif ($act === 'page-copy') {
        [$pkey, $text] = setLayoutPageCopy($lay, intval($bid));
        if ($pkey) [$key, $sel, $tab, $undo] = [$pkey, 'page:'.$pkey, 'page', true];
    } elseif ($act === 'page-text') {
        $src = getVar('post', 'text', 'raw', '');
        $src = (is_string($src) && mb_check_encoding($src, 'UTF-8') && strlen($src) <= 65535) ? $src : '';
        [$pkey, $title, $num] = setLayoutTextPage($lay, $types, $src);
        if (!$pkey) $warn = _LAYOUT_FROMNONE;
        else [$key, $sel, $tab, $undo, $text] = [$pkey, 'page:'.$pkey, 'page', true, sprintf(_LAYOUT_FROMDONE, $title, $num)];
    } elseif ($act === 'page-del') {
        $pkey = intval($bid);
        if (isset($lay['pages'][$pkey]) && !in_array($pkey, [$lay['home'], $lay['notfound']], true)) {
            $text = sprintf(_LAYOUT_PAGEDEL, getLayoutPageTitle($lay, $pkey));
            unset($lay['pages'][$pkey]);
            [$key, $sel, $undo] = [($key === $pkey) ? $lay['home'] : $key, '', true];
        }
    } elseif (str_starts_with($act, 'blk-')) {
        $new['layout'] = $lay;
        [$sel, $text, $undo] = setLayoutBlockAct($new, $places, $act, $bid, $arg, $sel);
        [$lay, $tab] = [$new['layout'], 'page'];
    }
    $new['layout'] = $lay;
    if ($sel !== '' && !isLayoutPicked($new, $sel)) $sel = '';
    if ($warn !== '') setLayoutPane($dft, $key, $sel, $tab, $warn, false, false, true);
    setLayoutKeep($dft, $new, $key, $sel, $tab, $text, $undo);
}

# The publications of the history, the newest first, each read from its file pub-<number>.json; a file that does not read is passed over
function getLayoutStory(): array {
    $out = [];
    foreach (glob(LAYOUT_DIR.'/pub-*.json') ?: [] as $file) {
        if (preg_match('/pub-([1-9][0-9]{0,9})\.json$/D', $file, $hit) !== 1) continue;
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data) && is_array($data['layout'] ?? null)) $out[intval($hit[1])] = $data;
    }
    krsort($out);
    return $out;
}

# Keep a publication in the history under the next number, those past the depth of Layout::HISTORY going with it
function addLayoutStory(array $one): bool {
    $list = getLayoutStory();
    $num = $list ? max(array_keys($list)) + 1 : 1;
    if (!setLayoutFile('pub-'.$num.'.json', ['num' => $num] + $one)) return false;
    foreach (array_slice(array_keys($list), Layout::HISTORY - 1) as $old) unlink(LAYOUT_DIR.'/pub-'.$old.'.json');
    return true;
}

function story(): void {
    global $tpl, $conf;
    $live = Layout::getLayoutHash($conf['layout'] ?? []);
    $types = getLayoutTypes(getLayoutView(getLayoutDraft()['layout']));
    $rows = [];
    $seen = false;
    foreach (getLayoutStory() as $num => $one) {
        $lay = Layout::getLayoutData($one['layout']);
        $secs = [];
        foreach ($lay['pages'] as $pkey => $page) {
            foreach ($page['sections'] as $sec) {
                $secs[] = ['arg' => $num.'-'.$pkey.'-'.intval($sec['id'] ?? 0), 'icon' => $types[(string)($sec['type'] ?? '')]['icon'] ?? 'square',
                    'where' => getLayoutPageTitle($lay, $pkey), 'text' => getLayoutSecLabel($sec, $types)];
            }
        }
        $text = sprintf(_LAYOUT_STORYCNT, count($lay['pages']), count($secs), count((array)($one['blocks'] ?? [])));
        $text = ((($one['by'] ?? '') !== '') ? (string)$one['by'] : '—').' · '.$text;
        $now = !$seen && Layout::getLayoutHash($lay) === $live;
        $seen = $seen || $now;
        $rows[] = ['num' => $num, 'when' => date(_TIMESTRING, intval($one['time'] ?? 0)), 'note' => (string)($one['note'] ?? ''), 'is_live' => $now,
            'text' => $text.($now ? ' · '._LAYOUT_STORYNOW : ''), 'secs' => $secs, 'secs_text' => sprintf(_LAYOUT_BACKONE, count($secs))];
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $tpl->getHtmlPart('layout-story', ['rows' => $rows, 'back_text' => _LAYOUT_STORYBACK, 'none_text' => _LAYOUT_STORYNONE, 'sec_text' => _LAYOUT_SECBACKTIP]);
    exit;
}

# The window before a publication: the changes against the site, the remarks, and the note and the publication when the publish button opens it
function checks(): void {
    global $tpl, $conf;
    $dft = getLayoutStored(getLayoutDraft());
    $lay = $dft['layout'];
    $view = getLayoutView($lay);
    $types = getLayoutTypes($view);
    $places = $view->getThemePlaces();
    $list = getLayoutChanges($dft, Layout::getLayoutData($conf['layout'] ?? []), $types, $places);
    $bad = getLayoutIssues($dft, $types, $places, getLayoutAnswer($lay), getLayoutWeight());
    $dirty = isLayoutDirty($dft);
    $sub = ($list ? sprintf(_LAYOUT_CHNUM, count($list)) : _LAYOUT_CHNONE).' · '.($bad ? sprintf(_LAYOUT_ISNUM, count($bad)) : _LAYOUT_ISNONE);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $tpl->getHtmlPart('layout-checks', ['title_text' => _LAYOUT_BEFORE, 'sub_text' => $sub, 'close_text' => _CLOSE, 'changes' => $list, 'issues' => $bad,
        'changes_title' => _LAYOUT_WILLCHANGE, 'issues_title' => _LAYOUT_ISSUES, 'changes_none' => _LAYOUT_CHSAME, 'issues_none' => _LAYOUT_ISGOOD,
        'changes_num' => count($list), 'issues_num' => count($bad),
        'is_pub' => $dirty && getVar('get', 'pub', 'num', 0) === 1, 'note_label' => _NOTE, 'note_hint' => _LAYOUT_NOTEHINT, 'note_place' => _LAYOUT_NOTEPLACE,
        'is_discard' => $dirty, 'discard_text' => _LAYOUT_DISCARD, 'discard_title' => _LAYOUT_DISCARDTIP, 'back_text' => _LAYOUT_BACKEDIT,
        'publish_text' => $bad ? _LAYOUT_PUBANYWAY : _LAYOUT_PUBLISH]);
    exit;
}

# The pages a section may be copied to: every page of the draft but its own
function dest(): void {
    global $tpl;
    $lay = getLayoutDraft()['layout'];
    $sel = getLayoutPick();
    $at = str_starts_with($sel, 'sec:') ? getLayoutSecPlace($lay, intval(substr($sel, 4))) : null;
    $rows = [];
    foreach (array_keys($lay['pages']) as $pkey) {
        if ($at === null || $pkey === $at[0]) continue;
        $where = ($pkey === $lay['home']) ? '/' : (($pkey === $lay['notfound']) ? _LAYOUT_LOST : '/'.(string)($lay['pages'][$pkey]['addr'] ?? ''));
        $rows[] = ['key' => $pkey, 'title' => getLayoutPageTitle($lay, $pkey), 'where' => $where];
    }
    $sec = ($at !== null) ? $lay['pages'][$at[0]]['sections'][$at[1]] : [];
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $tpl->getHtmlPart('layout-dest', ['title_text' => _LAYOUT_TOPAGE, 'sub_text' => getLayoutSecName($sec), 'close_text' => _CLOSE, 'rows' => $rows,
        'sid' => intval($sec['id'] ?? 0), 'none_text' => _LAYOUT_NOPAGES]);
    exit;
}

# Keep the picked section in my sections or drop one of them: a file the editors share, no step of the draft
function setLayoutMineAct(array $dft, string $act, int $num, int $key, string $sel, string $tab): never {
    $list = getLayoutMine();
    $text = '';
    if ($act === 'mine-del' && isset($list[$num])) {
        array_splice($list, $num, 1);
        $text = _LAYOUT_MINEDROP;
    } elseif ($act === 'sec-mine' && ($at = getLayoutSecPlace($dft['layout'], $num)) !== null) {
        $sec = $dft['layout']['pages'][$at[0]]['sections'][$at[1]];
        $types = getLayoutTypes(getLayoutView($dft['layout']));
        $one = $types[(string)($sec['type'] ?? '')] ?? null;
        if ($one === null || $one['one']) setLayoutPane($dft, $key, $sel, $tab, '', false, false);
        $name = getLayoutSecLabel($sec, $types);
        $list[] = ['time' => time(), 'name' => $name, 'sec' => array_diff_key($sec, ['id' => true])];
        $text = sprintf(_LAYOUT_MINEKEPT, $name);
    }
    if ($text === '') setLayoutPane($dft, $key, $sel, $tab, '', false, false);
    if (!setLayoutFile(LAYOUT_MINE, $list)) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_NOWRITE, false, true, true);
    setLayoutPane($dft, $key, $sel, $tab, $text, false, false);
}

# A link to look at the page of the draft for a term of days under the next counter of links, which takes back every earlier link and frame; the frame gets a new one
function setLayoutLink(array $dft, int $key, string $sel, string $tab, int $days): never {
    global $conf;
    $days = in_array($days, LAYOUT_TERMS, true) ? $days : LAYOUT_TERMS[1];
    $new = $dft;
    $new['link']++;
    $keep = getLayoutStored($new);
    if (!setLayoutDraft($keep)) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_NOWRITE, false, false, true);
    [$new['rev'], $new['at']] = [$keep['rev'], $keep['at']];
    $mod = isset($dft['made']) ? ['mod' => $dft['made']['mod']] : [];
    $till = time() + $days * 86400;
    $link = static fn(int $end, array $more): string => 'index.php?'.http_build_query(['name' => 'page-'.$key, 'draft' => Layout::getDraftToken($key, $end, $new['link'])] + $more);
    $evs = ['sl-layout-link' => ['url' => rtrim((string)($conf['homeurl'] ?? ''), '/').'/'.$link($till, $mod), 'till' => sprintf(_LAYOUT_LINKTILL, date(_TIMESTRING, $till)),
        'frame' => HOME_PATH.$link(time() + 7200, ['edit' => 1] + $mod), 'text' => _LAYOUT_LINKDONE]];
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('HX-Trigger: '.json_encode($evs));
    echo getLayoutChip($new, true);
    exit;
}

# The draft becomes the site again in one step with Undo: the layout, the blocks, the name and the logo as published, the counter of ids keeping its larger value
function setLayoutDiscard(array $dft, int $key, string $sel, string $tab): never {
    global $conf;
    $live = Layout::getLayoutData($conf['layout'] ?? []);
    $view = getLayoutView($dft['layout']);
    $num = count(getLayoutChanges($dft, $live, getLayoutTypes($view), $view->getThemePlaces()));
    $new = array_replace($dft, ['layout' => array_replace($live, ['next' => max($live['next'], $dft['layout']['next'])]), 'blocks' => [], 'bwas' => [], 'conf' => []]);
    if (!isset($new['layout']['pages'][$key])) $key = getLayoutPageKey($new['layout']);
    if ($sel !== '' && !isLayoutPicked($new, $sel)) $sel = '';
    setLayoutKeep($dft, $new, $key, $sel, $tab, sprintf(_LAYOUT_DISCARDED, $num), true);
}

# A layout file of JSON, never PHP, replaces the layout of the draft in one step with Undo; its blocks and settings stay, the counter of ids keeps its larger value
function setLayoutImport(array $dft, int $key, string $sel, string $tab): never {
    $file = $_FILES['file'] ?? null;
    $code = is_array($file) ? intval($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
    $name = is_array($file) ? basename((string)($file['name'] ?? '')) : '';
    $warn = match (true) {
        in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($code === UPLOAD_ERR_OK && intval($file['size']) > 1048576) => _ERROR_BIG,
        $code !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name']) => _ERROR_DOWN,
        strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json' => _ERROR_FILE,
        default => '',
    };
    $data = ($warn === '') ? json_decode((string)file_get_contents((string)$file['tmp_name']), true) : null;
    $view = getLayoutView($dft['layout']);
    [$lay, $bad] = is_array($data) ? getLayoutImport($data, $dft['layout'], getLayoutTypes($view), $view->getThemePlaces()) : [null, []];
    if ($warn === '' && $lay === null) $warn = $bad ? sprintf(_LAYOUT_IMPVAL, implode('; ', array_slice($bad, 0, 5))) : _LAYOUT_IMPBAD;
    if ($warn !== '') setLayoutPane($dft, $key, $sel, $tab, $warn, false, false, true);
    $new = array_replace($dft, ['layout' => $lay]);
    if (!isset($lay['pages'][$key])) $key = getLayoutPageKey($lay);
    if ($sel !== '' && !isLayoutPicked($new, $sel)) $sel = '';
    setLayoutKeep($dft, $new, $key, $sel, $tab, sprintf(_LAYOUT_IMPDONE, $name), true);
}

# Take one field of the pick back to its value on the site, a field of an item by its place in the list ("2-alt"); the answer is whether the pick and the field were found
function setLayoutFieldBack(array &$new, array $places, string $sel, string $arg): bool {
    global $conf;
    if (preg_match('/^(?:([0-9]{1,3})-)?([a-z][a-z0-9]{0,31})$/D', $arg, $hit) !== 1) return false;
    [$num, $fkey] = [$hit[1], $hit[2]];
    $part = explode(':', $sel);
    if ($part[0] === 'blk') {
        $blk = getLayoutBlocks($new)[$part[1] ?? ''] ?? null;
        $row = getLayoutBlocks(['blocks' => []])[$part[1] ?? ''] ?? null;
        if ($blk === null || $row === null || $num !== '' || !array_key_exists($fkey, $row)) return false;
        setLayoutBlock($new, $blk, [$fkey => $row[$fkey]]);
        return true;
    }
    $live = Layout::getLayoutData($conf['layout'] ?? []);
    $lay = $new['layout'];
    [$path, $from, $list] = [null, null, 'items'];
    if ($part[0] === 'sec' && ($at = getLayoutSecPlace($lay, intval($part[1] ?? 0))) !== null && ($was = getLayoutSecPlace($live, intval($part[1]))) !== null) {
        [$path, $from] = [['pages', $at[0], 'sections', $at[1]], $live['pages'][$was[0]]['sections'][$was[1]]];
    } elseif ($part[0] === 'part' && is_array($places['parts'][$part[1] ?? ''] ?? null)) {
        [$path, $from, $list] = [['theme'], $live['theme'], (string)($places['parts'][$part[1]]['items']['key'] ?? '')];
    } elseif ($part[0] === 'page' && isset($lay['pages'][intval($part[1] ?? 0)], $live['pages'][intval($part[1] ?? 0)])) {
        [$path, $from] = [['pages', intval($part[1])], $live['pages'][intval($part[1])]];
    } elseif ($part[0] === 'menu' && isset($part[2])) {
        $at = getLayoutItemPlace(is_array($lay['menus'][$part[1]] ?? null) ? $lay['menus'][$part[1]] : [], intval($part[2]));
        $olds = is_array($live['menus'][$part[1]] ?? null) ? $live['menus'][$part[1]] : [];
        $was = getLayoutItemPlace($olds, intval($part[2]));
        if ($at !== null && $was !== null) $path = isset($at[1]) ? ['menus', $part[1], $at[0], 'kids', $at[1]] : ['menus', $part[1], $at[0]];
        if ($was !== null) $from = isset($was[1]) ? $olds[$was[0]]['kids'][$was[1]] : $olds[$was[0]];
    }
    if ($path === null || !is_array($from)) return false;
    if ($num !== '') {
        $from = $from[$list][intval($num)] ?? null;
        $path = array_merge($path, [$list, intval($num)]);
        if (!is_array($from)) return false;
    }
    $box = &$new['layout'];
    foreach ($path as $step) {
        if (!is_array($box) || !array_key_exists($step, $box)) return false;
        $box = &$box[$step];
    }
    if (!is_array($box)) return false;
    if (array_key_exists($fkey, $from)) $box[$fkey] = $from[$fkey];
    else unset($box[$fkey]);
    unset($box);
    return true;
}

# Write the blocks of the draft inside the transaction of the publication: a row changed or gone since the draft touched it stops it by name; the answer is the ids of the new rows
function setLayoutRows(array $dft, array $rss): array {
    global $db;
    $now = getLayoutBlocks(['blocks' => []]);
    $put = static function (string $sql, array $pars) use ($db): void {
        if ($db->getSqlQuery($sql, $pars) === false) throw new RuntimeException('Layout: a row of the blocks cannot be written');
    };
    $cols = static function (array $all, array $set) use ($rss): array {
        $out = ['title' => mb_substr((string)$all['title'], 0, 60), 'bpos' => (string)$all['bpos'], 'weight' => max(1, intval($all['weight']))];
        $out += ['status' => empty($all['status']) ? 0 : 1, 'view' => intval($all['view']), 'lang' => (string)$all['lang'], 'action' => (string)$all['action']];
        $out['which'] = getBlockWhich(array_map('strval', (array)$all['which']));
        if ($all['kind'] === 'sys') return $out;
        $out['bfile'] = ($all['kind'] === 'file') ? (string)$all['bfile'] : '';
        $out['url'] = ($all['kind'] === 'rss') ? (string)$all['url'] : '';
        if (array_key_exists('bfile', $set) || array_key_exists('kind', $set)) $out['param'] = ($out['bfile'] === 'node.php') ? '{"type":[],"mode":"last","limit":10}' : '';
        if ($out['url'] !== '' && array_key_exists('url', $set)) [$out['content'], $out['time']] = [$rss[$out['url']] ?? '', time()];
        elseif (array_key_exists('content', $set)) $out['content'] = filterSourceText((string)$all['content'], true);
        return $out;
    };
    $skip = ['days' => true, 'kind' => true];
    [$zones, $made] = [[], []];
    foreach ($dft['blocks'] as $bid => $set) {
        if (!is_array($set)) continue;
        $days = intval($set['days'] ?? 0);
        $till = ($days > 0) ? 'NOW() + INTERVAL :days DAY' : 'NULL';
        if (!empty($set['new'])) {
            $vals = $cols($set, $set);
            $sql = '(bkey, refresh, expire, '.implode(', ', array_keys($vals)).') VALUES (\'\', 3600, '.$till.', :'.implode(', :', array_keys($vals)).')';
            $put('INSERT INTO '.PREFIX_DB.'_blocks '.$sql, $vals + (($days > 0) ? ['days' => $days] : []));
            $made[] = intval($db->getSqlLastId());
            $zones[$vals['bpos']] = true;
            continue;
        }
        $row = $now[$bid] ?? null;
        $was = is_array($dft['bwas'][$bid] ?? null) ? $dft['bwas'][$bid] : [];
        $name = getConst((string)(($row ?? $was)['title'] ?? $bid));
        if ($row === null || array_diff_key(array_intersect_key($row, $was), $skip) !== array_diff_key(array_intersect_key($was, $row), $skip)) {
            throw new DomainException(sprintf(_LAYOUT_BLKMOVED, $name, _BLOCKS));
        }
        $zones[$row['bpos']] = true;
        if (!empty($set['del'])) {
            if ($row['kind'] !== 'sys') $put('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => intval($bid)]);
            continue;
        }
        $vals = $cols(array_replace($row, $set), $set);
        $zones[$vals['bpos']] = true;
        $sql = implode(', ', array_map(static fn(string $v): string => $v.' = :'.$v, array_keys($vals))).(array_key_exists('days', $set) ? ', expire = '.$till : '');
        $put('UPDATE '.PREFIX_DB.'_blocks SET '.$sql.' WHERE id = :id', $vals + ['id' => intval($bid)] + (($days > 0 && array_key_exists('days', $set)) ? ['days' => $days] : []));
    }
    foreach (array_intersect(array_map('strval', array_keys($zones)), ['l', 'r', 'c', 'd']) as $zone) {
        if (!setBlockOrder($zone)) throw new RuntimeException('Layout: a zone of the blocks cannot be renumbered');
    }
    return $made;
}

# Publish the draft: the layout, the addresses and the blocks in one transaction, then the name and the logo of the site, the history and a draft equal to the site
function setLayoutPublish(array $dft, int $key, string $sel, string $tab): never {
    global $db, $conf, $admin;
    if (!isLayoutDirty($dft)) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_PUBNONE, false, false, true);
    $bids = array_map('intval', array_values(array_filter(array_keys($dft['blocks']), static fn($v): bool => preg_match('/^[1-9][0-9]*$/D', (string)$v) === 1)));
    $rss = [];
    foreach ($dft['blocks'] as $set) if (is_array($set) && empty($set['del']) && (string)($set['url'] ?? '') !== '') $rss[$set['url']] = getRssBody((string)$set['url']) ?? '';
    $work = ($dft['blocks'] !== []) ? static fn(): array => setLayoutRows($dft, $rss) : null;
    $res = Layout::setPublish($db, $dft['layout'], getVar('post', 'live', 'var', ''), $bids, $work);
    $by = substr((string)($admin[1] ?? ''), 0, 25);
    if ($res['state'] !== 'committed') {
        $last = getLayoutStory();
        $last = $last[array_key_first($last) ?? 0] ?? [];
        $who = (($last['layout'] ?? null) && Layout::getLayoutHash($last['layout']) === Layout::getLayoutHash($conf['layout'] ?? [])) ? (string)($last['by'] ?? '') : '';
        $text = match ($res['state']) {
            'stale' => sprintf(_LAYOUT_PUBSTALE, ($who !== '') ? $who : '—', date(_TIMESTRING, intval(filemtime(CONFIG_DIR.'/layout.php')))),
            'refused' => $res['text'],
            'address' => sprintf(_LAYOUT_ADDRBAD, getLayoutPageTitle($dft['layout'], intval($res['key']))),
            'uncertain' => sprintf(_LAYOUT_PUBUNSURE, _PREFERENCES, _CONFIG_RESTORE),
            default => _LAYOUT_PUBFAIL,
        };
        setLayoutPane($dft, $key, $sel, $tab, $text, false, true, true);
    }
    Logger::addSite('info', 'Layout: the draft was published', $res['proof'] + ['by' => $by]);
    $conf['layout'] = $res['data'];
    $kept = [];
    if ($dft['conf'] !== []) {
        $file = CONFIG_DIR.'/global.php';
        if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
        $glob = is_file($file) ? require $file : [];
        $kept = (is_array($glob) && setConfigFile('global.php', $dft['conf'], $glob)) ? [] : $dft['conf'];
        if ($kept === []) $conf = array_replace($conf, $dft['conf']);
    }
    $site = ['sitename' => (string)($conf['sitename'] ?? ''), 'site_logo' => (string)($conf['site_logo'] ?? '')];
    $note = getVar('post', 'note', 'raw', '');
    $note = (is_string($note) && mb_check_encoding($note, 'UTF-8')) ? mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $note)), 0, 120) : '';
    if (!addLayoutStory(['time' => time(), 'by' => $by, 'note' => $note, 'layout' => $res['data'], 'blocks' => getLayoutBlocks(['blocks' => []]), 'conf' => $site])) {
        Logger::addSite('warning', 'Layout: the publication was not kept in the history', ['by' => $by]);
    }
    $next = array_replace($dft, ['layout' => $res['data'], 'blocks' => [], 'bwas' => [], 'conf' => $kept, 'undo' => [], 'redo' => []]);
    $text = !setLayoutDraft($next) ? _LAYOUT_NOWRITE : ($kept ? _LAYOUT_CONFKEPT : _LAYOUT_PUBDONE);
    setLayoutPane($next, $key, $sel, $tab, $text, false, true, $text !== _LAYOUT_PUBDONE);
}

# Bring one publication of the history back as the draft: its layout with the larger counter, its blocks laid over the rows as they stand now, its name and logo of the site
function setLayoutStoryBack(array $dft, int $num, int $key, string $sel, string $tab): never {
    global $conf;
    $one = getLayoutStory()[$num] ?? null;
    if ($one === null) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_STORYGONE, false, false, true);
    $new = array_replace($dft, ['layout' => Layout::getLayoutData($one['layout']), 'blocks' => [], 'conf' => []]);
    $new['layout']['next'] = max($new['layout']['next'], $dft['layout']['next']);
    $rows = getLayoutBlocks(['blocks' => []]);
    $then = is_array($one['blocks'] ?? null) ? $one['blocks'] : [];
    $skip = ['id' => true, 'key' => true, 'new' => true, 'days' => true, 'expire' => true];
    foreach ($rows as $bid => $row) {
        if (is_array($then[$bid] ?? null)) {
            setLayoutBlock($new, $row, array_intersect_key(array_diff_key($then[$bid], $skip + (($row['kind'] === 'rss') ? ['content' => true] : [])), $row));
        } elseif ($row['kind'] !== 'sys') {
            $new['bwas'][$bid] = array_diff_key($row, ['new' => true]);
            $new['blocks'][$bid] = ['del' => true];
        }
    }
    foreach ($then as $bid => $old) {
        if (isset($rows[$bid]) || !is_array($old) || ($old['kind'] ?? '') === 'sys') continue;
        $nid = 'n'.(++$new['bnext']);
        $new['blocks'][$nid] = array_replace($old, ['id' => $nid, 'new' => true, 'days' => 0, 'expire' => '']);
    }
    foreach (['sitename', 'site_logo'] as $ckey) {
        if (is_string($one['conf'][$ckey] ?? null) && $one['conf'][$ckey] !== (string)($conf[$ckey] ?? '')) $new['conf'][$ckey] = $one['conf'][$ckey];
    }
    if (!isset($new['layout']['pages'][$key])) $key = getLayoutPageKey($new['layout']);
    if ($sel !== '' && !isLayoutPicked($new, $sel)) $sel = '';
    setLayoutKeep($dft, $new, $key, $sel, $tab, sprintf(_LAYOUT_STORYDONE, date(_TIMESTRING, intval($one['time'] ?? 0))), true);
}

# Take one step back or forward: the state of the draft changes places with the one kept, the counter of ids keeps its larger value
function setLayoutBack(array $dft, int $key, string $sel, string $tab, bool $back): never {
    $from = $back ? 'undo' : 'redo';
    $to = $back ? 'redo' : 'undo';
    if ($dft[$from] === []) setLayoutPane($dft, $key, $sel, $tab, $back ? _LAYOUT_NOUNDO : _LAYOUT_NOREDO, false, false, true);
    $new = $dft;
    $new[$to][] = getLayoutState($dft);
    $state = array_pop($new[$from]);
    foreach (['layout', 'blocks', 'conf'] as $one) $new[$one] = is_array($state[$one] ?? null) ? $state[$one] : [];
    $new['layout'] = Layout::getLayoutData($new['layout']);
    $new['layout']['next'] = max($dft['layout']['next'], $new['layout']['next']);
    if (!isset($new['layout']['pages'][$key])) $key = getLayoutPageKey($new['layout']);
    if ($sel !== '' && !isLayoutPicked($new, $sel)) $sel = '';
    if (!setLayoutDraft($new)) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_NOWRITE, false, true, true);
    setLayoutPane($new, $key, $sel, $tab, $back ? _LAYOUT_UNDONE : _LAYOUT_REDONE, false);
}

# The text field of the pick a window edits: its description, its value and the name it posts under, a field of an item by its place; only a kind markdown or code answers
function getLayoutText(array $lay, string $sel, string $fkey, string $num): ?array {
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $part = explode(':', $sel);
    [$desc, $vals] = [null, null];
    if ($part[0] === 'sec' && ($at = getLayoutSecPlace($lay, intval($part[1] ?? 0))) !== null) {
        $vals = $lay['pages'][$at[0]]['sections'][$at[1]];
        $desc = getLayoutTypes($view)[(string)($vals['type'] ?? '')]['desc'] ?? null;
    } elseif ($part[0] === 'part' && is_array($places['parts'][$part[1] ?? ''] ?? null)) {
        [$desc, $vals] = [$places['parts'][$part[1]], $lay['theme']];
    }
    if (!is_array($desc)) return null;
    $list = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
    $name = 'field['.$fkey.']';
    if ($num !== '') {
        $items = is_array($desc['items'] ?? null) ? $desc['items'] : [];
        $from = ($part[0] === 'sec') ? ($vals['items'] ?? null) : ($vals[(string)($items['key'] ?? '')] ?? null);
        $vals = is_array($from) ? (array_values($from)[intval($num)] ?? null) : null;
        [$list, $name] = [is_array($items['fields'] ?? null) ? $items['fields'] : [], 'item['.intval($num).']['.$fkey.']'];
    }
    foreach ($list as $one) {
        if (is_array($vals) && ($one['key'] ?? '') === $fkey && in_array($one['type'] ?? '', ['markdown', 'code'], true)) return [$one, (string)($vals[$fkey] ?? ''), $name];
    }
    return null;
}

# The editor window: a Markdown text of the pick in the editor the site has chosen, with its file window and preview, the own HTML in the code editor, or a page from a text
function edit(): void {
    global $tpl;
    $dft = getLayoutDraft();
    $lay = $dft['layout'];
    $key = getLayoutPageKey($lay);
    $sel = getLayoutPick();
    $from = getVar('get', 'from', 'num', 0) === 1;
    $num = getVar('get', 'num', 'raw', '');
    $num = (is_string($num) && preg_match('/^[0-9]{1,3}$/D', $num) === 1) ? $num : '';
    $text = $from ? null : getLayoutText($lay, $sel, getVar('get', 'fld', 'var', ''), $num);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$from && $text === null) {
        echo $tpl->getHtmlFrag('alert', ['text' => _NONE_FOUND, 'meta' => '', 'type' => 'info', 'is_warn' => true]);
        exit;
    }
    $code = !$from && $text[0]['type'] === 'code';
    $label = $from ? _LAYOUT_FROMTITLE : getConst((string)$text[0]['label']);
    $hint = explode('|', _LAYOUT_FROMHINT) + ['', '', '', ''];
    $hint = '# '.$hint[0]."\n\n".$hint[1]."\n\n## ".$hint[2]."\n\n".$hint[3];
    $html = $code ? Editor::getCode(['id' => 'f-lay-edit', 'name' => $text[2], 'label' => $label, 'lang' => 'html', 'text' => $text[1]])
        : getTplTextarea(['id' => 'f-lay-edit', 'name' => $from ? 'text' : $text[2], 'value' => $from ? '' : $text[1], 'mod' => 'layout', 'store' => 'config', 'label' => $label,
            'rows' => 18, 'placeholder' => $from ? $hint : '']);
    echo $tpl->getHtmlPart('layout-edit', ['icon_name' => $from ? 'file-earmark-text' : ($code ? 'code-slash' : 'markdown'), 'title_text' => $label,
        'sub_text' => $from ? _LAYOUT_FROMSUB : ($code ? _LAYOUT_HTMLSUB : _LAYOUT_MDSUB), 'close_text' => _CLOSE, 'post_url' => getLayoutUrl(['op' => $from ? 'act' : 'save']),
        'token' => getSiteToken('layout'), 'sel' => $sel, 'page' => $key, 'is_from' => $from, 'editor_html' => $html, 'cancel_text' => _CANCEL,
        'save_text' => $from ? _LAYOUT_MAKEPAGE : _SAVE, 'save_icon' => $from ? 'file-earmark-plus' : 'check-lg']);
    exit;
}

# The published file of the layout in the code editor of the panel, read-only, as the screen of templates shows a file; the draft is edited by the builder alone
function source(): void {
    global $tpl;
    $file = CONFIG_DIR.'/layout.php';
    $text = is_file($file) ? (string)file_get_contents($file) : '';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $when = is_file($file) ? date(_TIMESTRING, intval(filemtime($file))) : '—';
    echo $tpl->getHtmlFrag('alert', ['text' => _LAYOUT_FILENOTE, 'meta' => '', 'type' => 'info', 'is_warn' => false]).Editor::getCode(['id' => 'f-lay-file', 'name' => '',
        'label' => _FILE.': config/layout.php · '._DATE.': '.$when, 'lang' => 'php', 'text' => $text, 'readonly' => true]);
    exit;
}

function find(): void {
    $find = mb_substr(trim((string)getVar('get', 'find', 'word', '')), 0, 100);
    $key = getVar('get', 'key', 'var', '');
    $now = (preg_match(Layout::FIELD, $key) === 1) ? intval(getVar('get', 'field['.$key.']', 'num', 0)) : 0;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo getLayoutNodeHtml($now, $find);
    exit;
}

function export(): void {
    $lay = Layout::getLayoutData(getLayoutStored(getLayoutDraft())['layout']);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="layout-'.date('Y-m-d').'.json"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($lay, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=layout', 'name=layout&op=info'],
        'tabs' => [_LAYOUT_BUILDER, _MANUAL],
    ]);
}

switch ($op) {
    default: layout(); break;
    case 'tree': tree(); break;
    case 'pick': pick(); break;
    case 'save': save(); break;
    case 'act': act(); break;
    case 'story': story(); break;
    case 'checks': checks(); break;
    case 'dest': dest(); break;
    case 'edit': edit(); break;
    case 'source': source(); break;
    case 'find': find(); break;
    case 'export': export(); break;
    case 'info': info(); break;
}
