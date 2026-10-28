<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The steps Ctrl+Z takes back, kept in the draft beside its layout
const LAYOUT_STEPS = 50;

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

# The words of a section in the tree: the title it carries, the module of its slot, or nothing
function getLayoutSecName(array $sec): string {
    if (($sec['type'] ?? '') === Layout::SLOT) {
        $mod = (string)($sec['module'] ?? '');
        return ($mod === '') ? _HOME : getModuleName($mod);
    }
    $title = trim((string)($sec['title'] ?? ''));
    return (mb_strlen($title) > 40) ? mb_substr($title, 0, 39).'…' : $title;
}

# The section types the library offers: the types of the theme and the modules, then the slot of a module, which the core owns
function getLayoutTypes(Layout $view): array {
    $out = [];
    foreach ($view->getSectionTypes() as $type => $one) {
        if ($one['root'] !== 'theme') getLang($one['root']);
        $desc = $one['desc'];
        $out[$type] = ['title' => getConst((string)($desc['title'] ?? $type)), 'note' => getConst((string)($desc['note'] ?? '')), 'icon' => (string)($desc['icon'] ?? 'square'),
            'group' => (string)($desc['group'] ?? 'master'), 'desc' => $desc, 'frag' => $one['frag'] !== '', 'one' => false];
    }
    $out[Layout::SLOT] = ['title' => _LAYOUT_SLOT, 'note' => _LAYOUT_SLOT_INFO, 'icon' => 'puzzle', 'group' => 'content', 'frag' => true, 'one' => true,
        'desc' => ['fields' => [['key' => 'module', 'label' => '_MODUL', 'type' => 'choice', 'options' => getLayoutModules()]], 'make' => ['module' => '']]];
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
    $out[] = ['key' => 'side', 'label' => '_LAYOUT_SIDE', 'type' => 'choice',
        'options' => ['0' => '_BLOCKS_MOD0', '1' => '_BLOCKS_MOD1', '2' => '_BLOCKS_MOD2', '3' => '_BLOCKS_MOD3']];
    $out[] = ['key' => 'top', 'label' => '_LAYOUT_TOP', 'type' => 'choice',
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
        default => false,
    };
    if (!$good) return ['format', null];
    if ($type === 'markdown' || $type === 'code') return ['', str_replace(["\r\n", "\r"], "\n", $raw)];
    if ($type === 'choice') return ['', (string)$raw];
    return ['', ($type === 'slug') ? filterSlug($text) : $text];
}

# Take the posted values of a list of fields into an array of values; a field missing from the post keeps its value, a refused one is named
function setLayoutValues(array $list, array $post, array &$vals, array &$bad): void {
    foreach ($list as $one) {
        $key = (string)($one['key'] ?? '');
        if ($key === '' || !array_key_exists($key, $post)) continue;
        if ($one['type'] === 'checks') continue;
        [$err, $val] = getLayoutValue($one, $post[$key]);
        if ($err !== '') {
            $bad[] = getConst((string)$one['label']);
            continue;
        }
        $vals[$key] = $val;
    }
}

# The rows of the inspector for a list of fields: a type of Field drawn by Field, a hint of show and a kind of the builder by the canon, named by its place in the post
function getLayoutRows(array $list, array $vals, string $base, string $tab = ''): array {
    global $tpl;
    $rows = [];
    $fld = new Field();
    foreach ($list as $one) {
        $key = (string)($one['key'] ?? '');
        if ($key === '' || (string)($one['tab'] ?? '') !== $tab) continue;
        $type = (string)$one['type'];
        $show = (string)($one['show'] ?? '');
        $name = $base.'['.$key.']';
        $fid = 'f-lay-'.trim((string)preg_replace('/[^a-z0-9]+/', '-', $base.'-'.$key), '-');
        $label = getConst((string)$one['label']);
        $val = $vals[$key] ?? null;
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
            $area = in_array($type, ['markdown', 'code'], true);
            $row['field_html'] = $area
                ? $tpl->getHtmlFrag('textarea', ['name_attr' => $name, 'input_id' => $fid, 'value_text' => is_string($val) ? $val : '', 'rows_num' => 6])
                : $tpl->getHtmlFrag('input', ['name_attr' => $name, 'input_id' => $fid, 'value_attr' => is_scalar($val) ? (string)$val : '', 'maxlength_num' => 2048,
                    'placeholder_text' => ($type === 'file') ? _LAYOUT_FILEHINT : '']);
        }
        if (isset($one['when'])) $row['attr'] = 'data-sl-show-when="'.htmlspecialchars($base.'['.$one['when'][0].']', ENT_QUOTES, 'UTF-8').'" data-sl-show-value="'
            .htmlspecialchars($one['when'][1], ENT_QUOTES, 'UTF-8').'"';
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

# The rows of a list of items, each a block of the form canon with its fields, its tools and its place among the others
function getLayoutItems(array $desc, mixed $list, string $owner): string {
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
            'content_html' => $tpl->getHtmlPart('div', ['rows' => getLayoutRows($fields, $item, 'item['.$i.']')])]);
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
        return $more + ['link' => ['href' => $link($one, $to), 'title' => $label, 'icon_name' => $icon, 'label' => $label, 'is_current' => $one === $sel],
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
        $addr = ($key === $lay['notfound']) ? _LAYOUT_LOST : (($key === $lay['home']) ? '/' : '/'.(string)($page['addr'] ?? ''));
        $icon = ($key === $lay['notfound']) ? 'sign-stop' : (($key === $lay['home']) ? 'house-door' : 'file-earmark-richtext');
        $groups[] = ['rows' => [$row('page:'.$key, $icon, getLayoutPageTitle($lay, $key), $addr, 'page')]];
        $groups[] = ['title' => _LAYOUT_AT_HEAD, 'icon' => 'window', 'chip' => _LAYOUT_AT_ALL, 'rows' => $parts(['_LAYOUT_AT_TOP', '_LAYOUT_AT_HEAD', '_LAYOUT_AT_MENU'])];
        $secs = [];
        foreach ($page['sections'] as $sec) {
            $sid = intval($sec['id'] ?? 0);
            $type = (string)($sec['type'] ?? '');
            $one = $types[$type] ?? ['title' => $type, 'icon' => 'question-square', 'frag' => false];
            $sub = getLayoutSecName($sec);
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
        $slot = $view->getSlotName($key);
        if ($slot !== null) $name = $slot;
        [$side, $top] = $view->getPageColumns($key);
        $open = ['l' => in_array($side, ['0', '1'], true), 'r' => in_array($side, ['0', '2'], true)];
        $open += ['c' => in_array($top, ['0', '1'], true), 'd' => in_array($top, ['0', '2'], true)];
        $free = [];
        foreach ($places['zones'] as $zone => $info) {
            $rows = [];
            foreach ($blks as $bid => $blk) {
                if ($blk['bpos'] !== $zone || in_array('infly', $blk['which'], true)) continue;
                $rows[] = getLayoutBlockRow($blk, $name, $key === $lay['home'], $row, $dial);
            }
            $groups[] = ['title' => getConst((string)($info['title'] ?? $zone)), 'icon' => 'layout-sidebar', 'is_sub' => true,
                'chip' => ($open[$zone] ?? true) ? '' : _LAYOUT_ZONEOFF,
                'rows' => $rows, 'drag' => 'blk-'.$zone, 'none' => $rows ? '' : _LAYOUT_ZONENONE];
        }
        foreach ($blks as $blk) if (in_array('infly', $blk['which'], true)) $free[] = getLayoutBlockRow($blk, $name, $key === $lay['home'], $row, $dial);
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
            foreach ($list as $i => $item) {
                $rows[] = getLayoutMenuRow($mkey, $item, 0, $depth, $i === 0, $row, $dial);
                foreach (is_array($item['kids'] ?? null) ? $item['kids'] : [] as $kid) {
                    $rows[] = getLayoutMenuRow($mkey, $kid, intval($item['id'] ?? 0), $depth, false, $row, $dial);
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
    return $tpl->getHtmlPart('layout-tree', ['is_oob' => $oob, 'tab' => $tab, 'tabs' => $tabs, 'tabs_label' => _LAYOUT_WHAT, 'menus' => $menus, 'menus_label' => _LAYOUT_MENUS,
        'groups' => $groups, 'note_html' => ($note !== '') ? $tpl->getHtmlFrag('alert', ['text' => $note, 'meta' => '', 'type' => 'info', 'is_warn' => false]) : '']);
}

# One row of a block in the tree: its kind, its state and whether this page shows it, its order in the zone
function getLayoutBlockRow(array $blk, string $pname, bool $phome, Closure $row, Closure $dial): array {
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
        'dial' => $dial([['blk-toggle', $on ? 'eye-slash' : 'eye', $on ? _LAYOUT_BLKOFF : _LAYOUT_BLKON, $blk['id']], ['blk-del', 'trash3', _DELETE, $blk['id']]])]);
}

# One row of a menu item in the tree: its place, the way to nest it into the item above or to bring it a level up, its deletion
function getLayoutMenuRow(string $mkey, array $item, int $up, int $depth, bool $first, Closure $row, Closure $dial): array {
    $iid = intval($item['id'] ?? 0);
    $href = (string)($item['href'] ?? '');
    $acts = [];
    if ($depth > 1 && $up) $acts[] = ['menu-out', 'arrow-bar-left', _LAYOUT_OUT, $iid];
    if ($depth > 1 && !$up && !$first && empty($item['kids'])) $acts[] = ['menu-in', 'arrow-bar-right', _LAYOUT_IN, $iid];
    $acts[] = ['menu-del', 'trash3', _LAYOUT_DELITEM, $iid];
    $icon = (string)($item['icon'] ?? '') ?: ($up ? 'arrow-return-right' : 'link-45deg');
    $grp = (string)$up;
    $attr = 'data-sl-drag-group="'.$grp.'" data-sl-drag-scope="'.$grp.'"'.($up ? ' data-sl-drag-parent="'.$up.'"' : '');
    return $row('menu:'.$mkey.':'.$iid, $icon, trim((string)($item['title'] ?? '')) ?: _LAYOUT_NEWITEM, ($href !== '') ? $href : _LAYOUT_DROPITEM, 'menu',
        ['key' => (string)$iid, 'is_drag' => true, 'row_attr' => $attr, 'is_kid' => $up > 0, 'is_off' => intval($item['who'] ?? 0) === 2, 'dial' => $dial($acts)]);
}

# The inspector of the pick: its head, its form by tabs and its foot; nothing picked shows the start of the screen
function getLayoutInsp(array $dft, int $key, string $sel, bool $oob = false): string {
    global $tpl;
    $lay = $dft['layout'];
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $part = explode(':', $sel);
    $data = ['is_oob' => $oob, 'save_url' => getLayoutUrl(['op' => 'save']), 'token' => getSiteToken('layout'), 'sel' => $sel, 'page' => $key, 'aria_text' => _LAYOUT_PICKED,
        'unpick_text' => _LAYOUT_UNPICK, 'tabs' => [], 'body_html' => '', 'acts' => [], 'main' => [], 'danger' => []];
    $note = static fn(string $text): string => $tpl->getHtmlFrag('alert', ['text' => $text, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    $grid = static fn(array $rows): string => $rows ? $tpl->getHtmlPart('div', ['rows' => $rows]) : '';
    if ($part[0] === 'sec' && ($at = getLayoutSecPlace($lay, intval($part[1]))) !== null) {
        $sec = $lay['pages'][$at[0]]['sections'][$at[1]];
        $sid = intval($sec['id']);
        $types = getLayoutTypes($view);
        $type = (string)($sec['type'] ?? '');
        $one = $types[$type] ?? null;
        if ($one === null) return getLayoutInsp($dft, $key, '', $oob);
        $desc = $one['desc'];
        $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
        $body = $grid(getLayoutRows($fields, $sec, 'field'));
        if (is_array($desc['items'] ?? null)) $body .= getLayoutItems($desc['items'], $sec['items'] ?? [], 's'.$sid);
        if ($type === Layout::SLOT) $body .= $note(sprintf(_LAYOUT_SLOTNOTE, getLayoutSecName($sec)));
        if (!$one['frag']) $body .= $note(_LAYOUT_NOFRAGIN);
        $data['tabs'][] = ['key' => 'cont', 'icon' => 'card-text', 'label' => _LAYOUT_TABCONT, 'body_html' => $body, 'is_first' => true];
        $look = $grid(getLayoutRows($fields, $sec, 'field', 'look'));
        if ($look !== '') $data['tabs'][] = ['key' => 'look', 'icon' => 'sliders', 'label' => _LAYOUT_TABLOOK, 'body_html' => $look, 'is_first' => false];
        $show = ['who' => '0', 'lang' => '', 'from' => '', 'till' => '', 'anchor' => '', 'phone' => true, 'desk' => true];
        $data['tabs'][] = ['key' => 'show', 'icon' => 'eye', 'label' => _LAYOUT_TABSHOW, 'is_first' => false,
            'body_html' => $grid(getLayoutRows(getLayoutShowFields(), array_replace($show, $sec), 'field')).$note(_LAYOUT_SHOWNOTE)];
        $on = !empty($sec['on']);
        $list = $lay['pages'][$at[0]]['sections'];
        if (!$one['one']) $data['acts'][] = ['act' => 'sec-dup', 'icon' => 'copy', 'title' => _LAYOUT_DUP, 'id' => $sid];
        $data['acts'][] = ['act' => 'sec-up', 'icon' => 'arrow-up', 'title' => _LAYOUT_UP, 'id' => $sid, 'is_off' => $at[1] === 0];
        $data['acts'][] = ['act' => 'sec-down', 'icon' => 'arrow-down', 'title' => _LAYOUT_DOWN, 'id' => $sid, 'is_off' => $at[1] === count($list) - 1];
        $data['acts'][] = ['act' => 'sec-toggle', 'icon' => $on ? 'eye-slash' : 'eye', 'title' => $on ? _LAYOUT_HIDESEC : _LAYOUT_SHOWSEC, 'id' => $sid];
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
        $body .= $grid(getLayoutRows($fields, $vals, 'field'));
        $list = is_array($one['items'] ?? null) ? $one['items'] : [];
        if (preg_match(Layout::FIELD, (string)($list['key'] ?? '')) === 1) $body .= getLayoutItems($list, $vals[$list['key']] ?? [], 'p'.$part[1]);
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
        $body = $grid(getLayoutRows(getLayoutItemFields($menu), $item, 'field'));
        $kids = count(is_array($item['kids'] ?? null) ? $item['kids'] : []);
        if ($kids) $body .= $note(sprintf(_LAYOUT_KIDSNOTE, $kids));
        $data['danger'] = ['act' => 'menu-del', 'label' => _LAYOUT_DELITEM, 'id' => intval($item['id'])];
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'link-45deg', 'title_text' => _LAYOUT_MITEM, 'sub_text' => $title, 'body_html' => $body]));
    }
    if ($part[0] === 'blk' && ($blk = getLayoutBlocks($dft)[$part[1]] ?? null) !== null) {
        $body = $grid(getLayoutRows(getLayoutBlockFields($blk, $places['zones']), $blk, 'field'));
        $body .= $note($blk['kind'] === 'sys' ? _LAYOUT_SYSNOTE : _LAYOUT_BLKNOTE);
        $on = $blk['status'];
        $data['acts'][] = ['act' => 'blk-toggle', 'icon' => $on ? 'eye-slash' : 'eye', 'title' => $on ? _LAYOUT_BLKOFF : _LAYOUT_BLKON, 'id' => $blk['id']];
        if (!$blk['new']) $data['acts'][] = ['href' => getLayoutBlockUrl($blk['id']), 'icon' => 'box-arrow-up-right', 'title' => _LAYOUT_INBLOCKS];
        if ($blk['kind'] !== 'sys') $data['danger'] = ['act' => 'blk-del', 'label' => _LAYOUT_DELBLK, 'id' => $blk['id']];
        $free = in_array('infly', $blk['which'], true);
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'bricks', 'title_text' => _LAYOUT_BLOCK,
            'sub_text' => $free ? sprintf(_LAYOUT_FREENUM, $blk['id']) : _LAYOUT_BLOCKS.' · '._LAYOUT_ALLSITE, 'body_html' => $body]));
    }
    if ($part[0] === 'page' && isset($lay['pages'][intval($part[1])])) {
        $pkey = intval($part[1]);
        $page = $lay['pages'][$pkey];
        $vals = ['title' => '', 'addr' => '', 'desc' => '', 'og' => '', 'noindex' => '0', 'who' => '0', 'band' => true, 'side' => '3', 'top' => '3'];
        $vals = array_replace($vals, $page);
        $vals['noindex'] = empty($page['noindex']) ? '0' : '1';
        $slot = $view->getSlotName($pkey);
        $lost = $pkey === $lay['notfound'];
        $body = $grid(getLayoutRows(getLayoutPageFields($lay, $pkey), $vals, 'field'));
        $say = ($pkey === $lay['home']) ? _LAYOUT_HOMENOTE : _LAYOUT_OWNNOTE;
        if ($slot !== null) $say = sprintf(_LAYOUT_SLOTPAGE, ($slot === '') ? _HOME : getModuleName($slot));
        if ($lost) $say = _LAYOUT_LOSTNOTE;
        $body .= $note($say).$note(_LAYOUT_COLSNOTE);
        if (!$lost && $pkey !== $lay['home']) $data['danger'] = ['act' => 'page-del', 'label' => _LAYOUT_DELPAGE, 'id' => $pkey];
        $title = $lost ? _LAYOUT_PAGE404 : (($pkey === $lay['home']) ? _HOME : _PAGE);
        return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => $lost ? 'sign-stop' : 'file-earmark-richtext', 'title_text' => $title,
            'sub_text' => sprintf(_LAYOUT_SECNUM, count($page['sections'])), 'body_html' => $body]));
    }
    $data['sel'] = '';
    $data['main'] = ['act' => 'page-add', 'arg' => '', 'icon' => 'file-earmark-plus', 'label' => _LAYOUT_NEWPAGE];
    $keys = [[['Ctrl', 'Z'], _LAYOUT_KEYUNDO], [['Ctrl', 'Shift', 'Z'], _LAYOUT_KEYREDO], [['Ctrl', 'K'], _LAYOUT_CMDS], [['Esc'], _LAYOUT_UNPICK]];
    $keys = $tpl->getHtmlPart('layout-keys', ['keys' => array_map(static fn(array $v): array => ['keys' => $v[0], 'text' => $v[1]], $keys)]);
    return $tpl->getHtmlPart('layout-insp', array_replace($data, ['icon_name' => 'cursor', 'title_text' => _LAYOUT_NOPICK, 'sub_text' => _LAYOUT_NOPICK2,
        'body_html' => $note(_LAYOUT_PICKHINT).$keys]));
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

# The state of the draft in the top bar: Undo and Redo, then the chip of a draft or of a published layout
function getLayoutChip(array $dft, bool $oob = false): string {
    global $tpl, $conf;
    $dirty = isLayoutDirty($dft);
    return $tpl->getHtmlPart('layout-state', ['is_oob' => $oob, 'is_dirty' => $dirty, 'state_text' => $dirty ? _LAYOUT_ISDRAFT : _LAYOUT_ISLIVE,
        'state_title' => $dirty ? _LAYOUT_DRAFTWAIT : '', 'undo_text' => _LAYOUT_UNDO, 'redo_text' => _LAYOUT_REDO,
        'is_undo' => $dft['undo'] !== [], 'is_redo' => $dft['redo'] !== [],
        'tools_label' => _LAYOUT_TOOLS, 'rev' => $dft['rev'], 'live' => Layout::getLayoutHash($conf['layout'] ?? []), 'publish_text' => _LAYOUT_PUBLISH]);
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

# Answer a write of the screen: the tree, the inspector and the state swapped out of band, the notice and the reload of the frame raised as events
function setLayoutPane(array $dft, int $key, string $sel, string $tab, string $text, bool $undo, bool $insp = true, bool $warn = false): never {
    $evs = ['sl-layout-saved' => ['text' => $warn ? '' : $text, 'undo' => $undo ? 1 : 0, 'page' => $key, 'sel' => $sel, 'tab' => $tab]];
    if ($warn && $text !== '') $evs['sl-quick-note'] = $text;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('HX-Trigger: '.json_encode($evs));
    echo getLayoutTree($dft, $key, $tab, $sel, true).($insp ? getLayoutInsp($dft, $key, $sel, true) : '').getLayoutChip($dft, true);
    exit;
}

# Keep a changed draft as one step and write it; a draft that cannot be written is refused with its reason, a refused value is named after the write
function setLayoutKeep(array $dft, array $new, int $key, string $sel, string $tab, string $text, bool $undo, bool $insp = true, bool $warn = false): never {
    if (getLayoutState($new) == getLayoutState($dft)) setLayoutPane($dft, $key, $sel, $tab, $warn ? $text : '', false, $insp || $warn, $warn);
    $new['undo'] = array_slice(array_merge($dft['undo'], [getLayoutState($dft)]), -LAYOUT_STEPS);
    $new['redo'] = [];
    if (!setLayoutDraft($new)) setLayoutPane($dft, $key, $sel, $tab, _LAYOUT_NOWRITE, false, true, true);
    setLayoutPane($new, $key, $sel, $tab, $text, $undo, $insp || $warn, $warn);
}

function layout(): void {
    global $tpl, $afile, $adminvars;
    $dft = getLayoutDraft();
    $made = is_file(LAYOUT_DIR.'/'.Layout::DRAFT) || setLayoutDraft($dft);
    $lay = $dft['layout'];
    $key = getLayoutPageKey($lay);
    $sel = getLayoutPick();
    if ($sel !== '' && !isLayoutPicked($dft, $sel)) $sel = '';
    $tab = getLayoutTab($sel);
    $view = getLayoutView($lay);
    $types = getLayoutTypes($view);
    $pages = [];
    foreach (array_keys($lay['pages']) as $one) {
        $pages[] = ['key' => $one, 'url' => getLayoutUrl(['page' => $one]), 'title' => getLayoutPageTitle($lay, $one), 'is_on' => $one === $key];
    }
    $cards = [];
    $taken = array_column($lay['pages'][$key]['sections'] ?? [], 'type');
    foreach ($types as $type => $one) {
        $off = !$one['frag'] || ($one['one'] && in_array($type, $taken, true));
        $why = !$one['frag'] ? _LAYOUT_NOFRAG : ($off ? sprintf(_LAYOUT_TAKEN, $one['title']) : $one['note']);
        $cards[] = ['group' => $one['group'], 'words' => mb_strtolower($one['title'].' '.$one['note']), 'url' => '#', 'title' => $one['title'], 'subtitle' => $why,
            'title_attr' => $why, 'thumb' => $type, 'is_off' => $off, 'link_attr' => 'data-sl-lay-add="'.$type.'"'.($off ? ' aria-disabled="true"' : '')];
    }
    $groups = [['key' => '', 'label' => _ALL, 'is_on' => true]];
    $names = ['base' => _LAYOUT_G_BASE, 'media' => _LAYOUT_G_MEDIA, 'content' => _LAYOUT_G_CONT];
    $names += ['trust' => _LAYOUT_G_TRUST, 'contact' => _LAYOUT_G_LINK, 'master' => _LAYOUT_G_PRO];
    foreach ($names as $one => $label) if (in_array($one, array_column($cards, 'group'), true)) $groups[] = ['key' => $one, 'label' => $label, 'is_on' => false];
    $frame = HOME_PATH.'index.php?'.http_build_query(['name' => 'page-'.$key, 'draft' => Layout::getDraftToken($key, time() + 7200, $dft['link'])]);
    setHead();
    $adminvars['is_full'] = true;
    if (!$made) echo $tpl->getHtmlFrag('alert', ['text' => _LAYOUT_NOWRITE, 'meta' => '', 'type' => 'error', 'is_warn' => true]);
    echo $tpl->getHtmlPart('layout-screen', [
        'act_url' => getLayoutUrl(['op' => 'act']), 'pick_url' => getLayoutUrl(['op' => 'pick']), 'tree_url' => getLayoutUrl(['op' => 'tree']), 'token' => getSiteToken('layout'),
        'page' => $key, 'sel' => $sel, 'tab' => $tab, 'panel_url' => HOME_PATH.$afile.'.php', 'panel_text' => _LAYOUT_PANEL, 'panel_title' => _ADMINMENU,
        'title_text' => _LAYOUT_BUILDER, 'page_label' => _PAGE, 'pages' => $pages, 'width_label' => _LAYOUT_WIDTH,
        'devices' => [['key' => 'desk', 'icon' => 'display', 'label' => _LAYOUT_DESK, 'is_on' => true], ['key' => 'tablet', 'icon' => 'tablet', 'label' => _LAYOUT_TABLET],
            ['key' => 'phone', 'icon' => 'phone', 'label' => _LAYOUT_PHONE]],
        'tools_label' => _LAYOUT_TOOLS, 'site_url' => HOME_PATH, 'site_text' => _LAYOUT_OPENSITE, 'cmds_text' => _LAYOUT_CMDS, 'help_url' => getLayoutUrl(['op' => 'info']),
        'help_text' => _MANUAL, 'sets_text' => _LAYOUT_SETS, 'help_label' => _LAYOUT_HELPSETS, 'state_html' => getLayoutChip($dft),
        'tree_label' => _LAYOUT_TREE, 'tree_html' => getLayoutTree($dft, $key, $tab, $sel), 'stage_label' => _LAYOUT_STAGE, 'frame_url' => $frame, 'frame_title' => _LAYOUT_FRAME,
        'insp_html' => getLayoutInsp($dft, $key, $sel), 'lib_title' => _LAYOUT_ADDSEC, 'lib_groups' => $groups, 'lib_groups_label' => _LAYOUT_GROUPS, 'lib_find' => _LAYOUT_FINDSEC,
        'lib_note' => _LAYOUT_LIBNOTE, 'cards' => $cards, 'none_text' => _NONE_FOUND, 'close_text' => _CLOSE,
        'sets_title' => _LAYOUT_SETS, 'where_title' => _LAYOUT_WHERE, 'links' => getLayoutLinks(), 'pal_title' => _LAYOUT_CMDS, 'pal_find' => _LAYOUT_PALFIND,
        'pal_acts' => getLayoutCommands(), 'pal_acts_title' => _ADM_ACTS, 'pal_pages_title' => _LAYOUT_PAGES, 'pal_secs_title' => _LAYOUT_SECS,
        'pal_pages' => $pages, 'undo_text' => _UNDO, 'ed' => bin2hex(random_bytes(6)), 'story_url' => getLayoutUrl(['op' => 'story']), 'story_title' => _LAYOUT_STORY,
        'story_sub' => _LAYOUT_STORYSUB,
    ]).getAdminIconWindow();
    setFoot();
}

# The commands of the palette of the screen, each with its icon and its keys
function getLayoutCommands(): array {
    $out = [
        ['undo', 'arrow-counterclockwise', _LAYOUT_UNDO, ['Ctrl', 'Z']],
        ['redo', 'arrow-clockwise', _LAYOUT_REDO, ['Ctrl', 'Shift', 'Z']],
        ['publish', 'cloud-arrow-up', _LAYOUT_PUBLISH, []],
        ['story', 'clock-history', _LAYOUT_STORY, []],
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
    foreach ($list as $i => $item) if (is_array($rows[$i] ?? null) && is_array($item)) setLayoutValues($fields, $rows[$i], $list[$i], $bad);
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
    setLayoutKeep($dft, $new, $key, $sel, $tab, $text, false, false, $bad !== []);
}

# Change the sections of the draft by one act of the tree, the foot or the library; the answer is the pick and the words of the notice
function setLayoutSecAct(array &$lay, array $types, string $act, int $sid, string $arg, int $key, string $sel): array {
    $show = ['who' => '0', 'lang' => '', 'from' => '', 'till' => '', 'anchor' => '', 'phone' => true, 'desk' => true];
    if ($act === 'sec-add') {
        $one = $types[$arg] ?? null;
        $list = $lay['pages'][$key]['sections'] ?? null;
        if ($one === null || !is_array($list)) return [$sel, '', false, ''];
        if ($one['one'] && in_array($arg, array_column($list, 'type'), true)) return [$sel, '', false, sprintf(_LAYOUT_TAKEN, $one['title'])];
        $sec = ['id' => $lay['next']++, 'type' => $arg, 'on' => true] + (is_array($one['desc']['make'] ?? null) ? getLayoutMake($one['desc']['make']) : []) + $show;
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
    }
    $lay['pages'][$pkey]['sections'] = $list;
    return $out;
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
    if ($act === 'undo' || $act === 'redo') setLayoutBack($dft, $key, $sel, $tab, $act === 'undo');
    if ($act === 'publish') setLayoutPublish($dft, $key, $sel, $tab);
    if ($act === 'story-back') setLayoutStoryBack($dft, intval($bid), $key, $sel, $tab);
    $new = $dft;
    $lay = $new['layout'];
    $view = getLayoutView($lay);
    $places = $view->getThemePlaces();
    $types = getLayoutTypes($view);
    [$text, $undo, $warn] = ['', false, ''];
    if (str_starts_with($act, 'sec-')) {
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
        $pkey = $lay['next']++;
        $lay['pages'][$pkey] = ['title' => _LAYOUT_NEWPAGE, 'addr' => '', 'desc' => '', 'band' => true, 'side' => '3', 'top' => '3', 'og' => '', 'noindex' => false,
            'who' => '0', 'sections' => []];
        $lay['pages'][$pkey]['addr'] = getLayoutAddr($lay, _LAYOUT_NEWPAGE, $pkey);
        [$key, $sel, $tab, $text] = [$pkey, 'page:'.$pkey, 'page', _LAYOUT_PAGEADD];
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
    $rows = [];
    $seen = false;
    foreach (getLayoutStory() as $num => $one) {
        $lay = Layout::getLayoutData($one['layout']);
        $secs = array_sum(array_map(static fn(array $v): int => count($v['sections']), $lay['pages']));
        $text = ((($one['by'] ?? '') !== '') ? (string)$one['by'] : '—').' · '.sprintf(_LAYOUT_STORYCNT, count($lay['pages']), $secs, count((array)($one['blocks'] ?? [])));
        $now = !$seen && Layout::getLayoutHash($lay) === $live;
        $seen = $seen || $now;
        $rows[] = ['num' => $num, 'when' => date(_TIMESTRING, intval($one['time'] ?? 0)), 'text' => $text.($now ? ' · '._LAYOUT_STORYNOW : ''), 'is_live' => $now];
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $tpl->getHtmlPart('layout-story', ['rows' => $rows, 'back_text' => _LAYOUT_STORYBACK, 'none_text' => _LAYOUT_STORYNONE]);
    exit;
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
            $sql = '(bkey, refresh, expire, '.implode(', ', array_keys($vals)).') VALUES (\'\', 0, '.$till.', :'.implode(', :', array_keys($vals)).')';
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
    if (!addLayoutStory(['time' => time(), 'by' => $by, 'layout' => $res['data'], 'blocks' => getLayoutBlocks(['blocks' => []]), 'conf' => $site])) {
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
    case 'info': info(); break;
}
