<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the draft of the site builder: the shipped functions of admin/modules/layout.php lifted out of their source and run over stubs
# Nothing boots the core or touches a database; LAYOUT_DIR is the scratch root the caller passed, so the draft is written there alone
# Modes: draft writes and reads the draft; value checks the values of fields; sec acts on sections; menu nests and orders items; block lays changes over a row
# The mode tools lists the changes and the remarks, pastes a look, copies a section, brings one back from a publication and makes a ready page
# The mode pages copies a page, makes a page from a text and lists the addresses an address field offers
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$pmode = (string)($argv[1] ?? '');
$pwork = str_replace('\\', '/', (string)($argv[2] ?? ''));
$psite = str_replace('\\', '/', dirname(__DIR__, 2));
if ($pwork === '' || !in_array($pmode, ['draft', 'value', 'sec', 'menu', 'block', 'tools', 'pages'], true)) {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork)) mkdir($pwork, 0777, true);
define('FUNC_FILE', true);
define('BASE_DIR', $psite);
define('PUBLIC_DIR', $psite.'/public');
define('LAYOUT_DIR', $pwork.'/layout');
define('LAYOUT_STEPS', 50);
$pwords = ['_LAYOUT_TAKEN' => 'taken %s', '_LAYOUT_ADDED' => 'added %s', '_LAYOUT_SECDEL' => 'deleted %s', '_LAYOUT_SECSHOWN' => 'shown %s', '_LAYOUT_SECHID' => 'hidden %s'];
$pwords += ['_LAYOUT_SECDUP' => 'copied %s', '_LAYOUT_ITEMADD' => 'item added', '_LAYOUT_ITEMDEL' => 'item deleted', '_LAYOUT_PROBE' => 'word of a constant'];
$pwords += ['_LAYOUT_SECCOPY' => 'copied %s to %s', '_LAYOUT_LOOKDONE' => 'look into %s', '_LAYOUT_LOOKGONE' => 'look gone', '_LAYOUT_STORYGONE' => 'story gone'];
$pwords += ['_LAYOUT_BACKPAGE' => 'page %s gone', '_HOME' => 'Home', '_LAYOUT_PAGE404' => 'Lost', '_LAYOUT_NEWPAGE' => 'New page', '_LAYOUT_PAGES' => 'Pages'];
$pwords += ['_LAYOUT_BLOCKS' => 'Blocks', '_LAYOUT_AT_ALL' => 'All', '_MODULES' => 'Modules', '_LAYOUT_COPYOF' => 'Copy %s'];
$pwords += ['_LAYOUT_PAGECOPIED' => 'copied %s.', '_LAYOUT_SLOTKEPT' => 'Slot kept.'];
$pkind = ['PAGE', 'PAGESET', 'PAGEDEL', 'SECNEW', 'SECSET', 'SECDEL', 'SECORD', 'PART', 'SITE', 'ITEMNEW', 'ITEMSET', 'ITEMDEL', 'ITEMORD', 'BLKNEW', 'BLKSET', 'BLKDEL'];
foreach ($pkind as $pone) $pwords['_LAYOUT_CH_'.$pone] = strtolower($pone).' %s';
foreach (['NOTITLE', 'INTEXT', 'NOFILM', 'HTTPS', 'NOITEMS', 'DATES', 'EXPIRED', 'NODEV', 'HIDDEN', 'NODESC', 'NONAME'] as $pone) $pwords['_LAYOUT_IS_'.$pone] = strtolower($pone);
foreach (['ANCHOR', 'ADDR', 'ALT', 'MDALT', 'TWIN', 'NOWHERE', 'BLKNONE', 'NORSS'] as $pone) $pwords['_LAYOUT_IS_'.$pone] = strtolower($pone).' %s';
$pwords += ['_LAYOUT_IS_HEAVY' => 'heavy %s %s', '_LAYOUT_IS_TWINURL' => 'twinurl %s %s'];
foreach ($pwords as $key => $val) define($key, $val);
set_time_limit(20);
$pdone = ['error' => '', 'data' => []];

# Cut one top-level function out of a shipped source and load it into this process
function setProbeLift(string $file, array $funcs, string $into): void {
    $code = (string)file_get_contents($file);
    $out = "<?php\n";
    foreach ($funcs as $func) {
        $from = strpos($code, "\nfunction ".$func.'(');
        $end = ($from === false) ? false : strpos($code, "\n}\n", $from);
        if ($end === false) throw new RuntimeException('Function '.$func.' was not found');
        $out .= substr($code, $from, $end - $from + 3);
    }
    file_put_contents($into, $out);
    require $into;
}

define('LAYOUT_HEAVY', 512000);

# The value of a request the probe sets in pvars, a stub of the probe
function getVar(string $var, string $key, string $type = '', mixed $default = ''): mixed {
    return $GLOBALS['pvars'][$key] ?? $default;
}

# The name of a module as the panel shows it, a stub of the probe
function getModuleName(string $mod): string {
    return 'Mod '.$mod;
}

try {
    require_once $psite.'/core/classes/layout.php';
    require_once $psite.'/core/classes/field.php';
    setProbeLift($psite.'/core/system.php', ['filterSlug', 'getConst'], $pwork.'/lift_core.php');
    $plift = ['getLayoutDraft', 'setLayoutFile', 'setLayoutDraft', 'getLayoutState', 'isLayoutDirty', 'getLayoutSecPlace', 'getLayoutItemPlace', 'getLayoutValue'];
    $plift[] = 'setLayoutValues';
    $plift = array_merge($plift, ['setLayoutBlock', 'getLayoutMake', 'getLayoutAddr', 'setLayoutSecAct', 'setLayoutMenuAct', 'setLayoutItemAct', 'isLayoutSame']);
    $plift = array_merge($plift, ['getLayoutView', 'getLayoutPageTitle', 'getLayoutSecName', 'getLayoutFlat', 'getLayoutLookKeys', 'getLayoutChanges', 'getLayoutIssues']);
    $plift = array_merge($plift, ['setLayoutSecBack', 'setLayoutPresetPage', 'getLayoutSecLabel', 'setLayoutPageCopy', 'setLayoutTextPage', 'getLayoutAddrs']);
    $plift = array_merge($plift, ['getLayoutModules', 'setLayoutSlotPage', 'getLayoutStored']);
    setProbeLift($psite.'/admin/modules/layout.php', $plift, $pwork.'/lift_layout.php');
    $site = ['v' => 1, 'shipped' => false, 'next' => 20, 'home' => 1, 'notfound' => 2, 'theme' => ['phone' => '', 'social' => []],
        'menus' => ['main' => [['id' => 4, 'title' => 'A', 'kids' => []], ['id' => 5, 'title' => 'B', 'kids' => []],
            ['id' => 6, 'title' => 'C', 'kids' => [['id' => 7, 'title' => 'D', 'kids' => []]]]]],
        'pages' => [1 => ['title' => '', 'sections' => [['id' => 3, 'type' => 'module', 'on' => true, 'module' => '']]], 2 => ['title' => '', 'sections' => []],
            9 => ['title' => 'About', 'addr' => 'about', 'sections' => []]]];
    $conf = ['layout' => $site, 'theme' => ''];
    if ($pmode === 'draft') {
        $dft = getLayoutDraft();
        $pdone['data']['fresh'] = ['next' => $dft['layout']['next'], 'keys' => array_keys($dft), 'dirty' => isLayoutDirty($dft), 'file' => is_file(LAYOUT_DIR.'/'.Layout::DRAFT)];
        $dft['layout']['next'] = 25;
        $pdone['data']['counter'] = isLayoutDirty($dft);
        $dft['layout']['theme']['phone'] = '+49 1';
        $dft['link'] = 3;
        $dft['undo'][] = getLayoutState(getLayoutDraft());
        $pdone['data']['written'] = setLayoutDraft($dft);
        $pdone['data']['dirty'] = isLayoutDirty($dft);
        $pdone['data']['left'] = array_values(array_diff(scandir(LAYOUT_DIR) ?: [], ['.', '..']));
        $link = Layout::getDraftFile();
        $pdone['data']['link'] = ['link' => $link['link'], 'phone' => $link['layout']['theme']['phone'] ?? null, 'pages' => array_keys($link['layout']['pages'] ?? [])];
        $back = getLayoutDraft();
        $pdone['data']['back'] = ['undo' => count($back['undo']), 'phone' => $back['layout']['theme']['phone'], 'next' => $back['layout']['next']];
        file_put_contents(LAYOUT_DIR.'/'.Layout::DRAFT, '{"layout": "broken"');
        $pdone['data']['broken'] = getLayoutDraft()['layout']['theme']['phone'];
        $conf['modules'] = ['news' => ['type' => 1, 'active' => 1, 'side' => '2', 'top' => '9'], 'admin' => ['type' => 0, 'active' => 1]];
        $GLOBALS['pvars'] = ['mod' => 'admin'];
        $pdone['data']['panel'] = isset(getLayoutDraft()['made']);
        $GLOBALS['pvars'] = ['mod' => 'news'];
        $made = getLayoutDraft();
        $page = $made['layout']['pages'][$made['made']['key'] ?? 0] ?? [];
        $pdone['data']['made'] = ['made' => $made['made'] ?? null, 'page' => [$page['title'] ?? null, $page['side'] ?? null, $page['top'] ?? null, $page['addr'] ?? null],
            'slot' => array_intersect_key($page['sections'][0] ?? [], ['id' => 1, 'type' => 1, 'module' => 1, 'on' => 1]), 'dirty' => isLayoutDirty(getLayoutStored($made)),
            'stored' => array_keys(getLayoutStored($made)['layout']['pages']), 'next' => getLayoutStored($made)['layout']['next']];
        $made['layout']['pages'][20]['title'] = 'Changed';
        setLayoutDraft($made);
        $again = getLayoutDraft();
        $pdone['data']['kept'] = ['made' => isset($again['made']), 'title' => $again['layout']['pages'][20]['title'] ?? null, 'next' => $again['layout']['next'],
            'file' => array_values(array_intersect(['base', 'made'], array_keys(json_decode((string)file_get_contents(LAYOUT_DIR.'/'.Layout::DRAFT), true))))];
    } elseif ($pmode === 'value') {
        $check = static fn(array $one, mixed $raw): array => getLayoutValue($one + ['key' => 'k'], $raw);
        $pdone['data'] = [
            'text' => $check(['type' => 'text'], '  Hello  '),
            'textmax' => $check(['type' => 'text', 'max' => 3], 'Hello'),
            'url' => $check(['type' => 'url'], '/about'),
            'badurl' => $check(['type' => 'url'], 'no address'),
            'select' => $check(['type' => 'select', 'options' => ['two' => '2', 'three' => '3']], 'three'),
            'badselect' => $check(['type' => 'select', 'options' => ['two' => '2']], 'four'),
            'bool' => $check(['type' => 'bool'], '0'),
            'int' => $check(['type' => 'int', 'min' => 3, 'max' => 12], '7'),
            'bigint' => $check(['type' => 'int', 'min' => 3, 'max' => 12], '40'),
            'choice' => $check(['type' => 'choice', 'options' => ['0' => '_ALL', '3' => '_GUESTS']], '3'),
            'badchoice' => $check(['type' => 'choice', 'options' => ['0' => '_ALL']], '9'),
            'empty' => $check(['type' => 'choice', 'options' => ['' => '_HOME', 'news' => 'News']], ''),
            'focus' => $check(['type' => 'focus'], 'left top'),
            'badfocus' => $check(['type' => 'focus'], 'middle'),
            'tone' => $check(['type' => 'tone'], 'accent'),
            'icon' => $check(['type' => 'icon'], 'Bad Icon'),
            'anchor' => $check(['type' => 'anchor'], '1st'),
            'slug' => $check(['type' => 'slug'], 'О компании'),
            'file' => $check(['type' => 'file'], 'presentation/../config/db.php'),
            'markdown' => $check(['type' => 'markdown'], "A\r\nB"),
            'kind' => $check(['type' => 'unknown'], 'x'),
        ];
        $vals = ['title' => 'Old', 'href' => '/old'];
        $bad = [];
        $pform = [['key' => 'title', 'label' => '_LAYOUT_PROBE', 'type' => 'text'], ['key' => 'href', 'label' => 'Link', 'type' => 'url']];
        $pform[] = ['key' => 'lead', 'label' => 'L', 'type' => 'text'];
        setLayoutValues($pform, ['title' => 'New', 'href' => 'no address'], $vals, $bad);
        $pdone['data']['set'] = ['vals' => $vals, 'bad' => $bad];
    } elseif ($pmode === 'sec') {
        $lay = Layout::getLayoutData($site);
        $types = ['hero' => ['title' => 'Hero', 'one' => false, 'frag' => true, 'desc' => ['make' => ['title' => '_LAYOUT_PROBE', 'items' => [['text' => '_LAYOUT_PROBE']]]]],
            'module' => ['title' => 'Slot', 'one' => true, 'frag' => true, 'desc' => ['make' => ['module' => '']]]];
        $out = [];
        $out['add'] = setLayoutSecAct($lay, $types, 'sec-add', 0, 'hero', 1, 'sec:3');
        $out['slot'] = setLayoutSecAct($lay, $types, 'sec-add', 0, 'module', 1, '');
        $out['dup'] = setLayoutSecAct($lay, $types, 'sec-dup', 20, '', 1, 'sec:20');
        $out['dupslot'] = setLayoutSecAct($lay, $types, 'sec-dup', 3, '', 1, 'sec:3');
        $out['up'] = setLayoutSecAct($lay, $types, 'sec-up', 21, '', 1, 'sec:21');
        $out['order'] = array_column($lay['pages'][1]['sections'], 'id');
        setLayoutSecAct($lay, $types, 'sec-move', 3, '20-1', 1, 'sec:3');
        $out['moved'] = array_column($lay['pages'][1]['sections'], 'id');
        setLayoutSecAct($lay, $types, 'sec-move', 3, '21-0', 1, 'sec:3');
        $out['before'] = array_column($lay['pages'][1]['sections'], 'id');
        setLayoutSecAct($lay, $types, 'sec-move', 3, '99-0', 1, 'sec:3');
        $out['nowhere'] = array_column($lay['pages'][1]['sections'], 'id');
        setLayoutSecAct($lay, $types, 'sec-order', 0, '20-3', 1, '');
        $out['short'] = array_column($lay['pages'][1]['sections'], 'id');
        setLayoutSecAct($lay, $types, 'sec-order', 0, '20-3-21', 1, '');
        $out['ordered'] = array_column($lay['pages'][1]['sections'], 'id');
        $out['hide'] = setLayoutSecAct($lay, $types, 'sec-toggle', 21, '', 1, 'sec:21');
        $out['del'] = setLayoutSecAct($lay, $types, 'sec-del', 21, '', 1, 'sec:21');
        $hero = $lay['pages'][1]['sections'][0];
        $out['hero'] = ['id' => $hero['id'], 'title' => $hero['title'], 'item' => $hero['items'][0]['text'], 'who' => $hero['who'], 'phone' => $hero['phone']];
        $out['next'] = $lay['next'];
        $out['ids'] = array_column($lay['pages'][1]['sections'], 'id');
        $out['addr'] = [getLayoutAddr($lay, 'About', 12), getLayoutAddr($lay, 'About', 9), getLayoutAddr($lay, '!!!', 12)];
        $pdone['data'] = $out;
    } elseif ($pmode === 'menu') {
        $lay = Layout::getLayoutData($site);
        $places = ['menus' => ['main' => ['depth' => 2], 'flat' => ['depth' => 1]]];
        $ids = static function (array $list): array {
            $out = [];
            foreach ($list as $item) $out[] = $item['id'].($item['kids'] ? '('.implode(',', array_column($item['kids'], 'id')).')' : '');
            return $out;
        };
        $out = [];
        setLayoutMenuAct($lay, $places, 'menu-in', 'menu:main', 5, '');
        $out['in'] = $ids($lay['menus']['main']);
        setLayoutMenuAct($lay, $places, 'menu-in', 'menu:main', 4, '');
        $out['first'] = $ids($lay['menus']['main']);
        setLayoutMenuAct($lay, $places, 'menu-out', 'menu:main', 7, '');
        $out['out'] = $ids($lay['menus']['main']);
        setLayoutMenuAct($lay, $places, 'menu-order', 'menu:main', 0, '6-7-4');
        $out['order'] = $ids($lay['menus']['main']);
        setLayoutMenuAct($lay, $places, 'menu-order', 'menu:main', 0, '6-4');
        $out['short'] = $ids($lay['menus']['main']);
        $out['del'] = setLayoutMenuAct($lay, $places, 'menu-del', 'menu:main:4', 4, '');
        $out['after'] = $ids($lay['menus']['main']);
        $lay['menus']['flat'] = [['id' => 30, 'kids' => []], ['id' => 31, 'kids' => []]];
        setLayoutMenuAct($lay, $places, 'menu-in', 'menu:flat', 31, '');
        $out['flat'] = $ids($lay['menus']['flat']);
        $out['unknown'] = setLayoutMenuAct($lay, $places, 'menu-del', 'menu:none', 4, '');
        $pdone['data'] = $out;
    } elseif ($pmode === 'tools') {
        $live = Layout::getLayoutData($site);
        $fields = [['key' => 'title', 'type' => 'text'], ['key' => 'href', 'type' => 'url'], ['key' => 'text', 'type' => 'markdown'],
            ['key' => 'pic', 'type' => 'bool', 'tab' => 'look'], ['key' => 'photo', 'type' => 'file', 'tab' => 'look', 'when' => ['pic', '1']],
            ['key' => 'alt', 'type' => 'text', 'tab' => 'look', 'when' => ['pic', '1']], ['key' => 'tone', 'type' => 'tone', 'tab' => 'look'],
            ['key' => 'cols', 'type' => 'select', 'show' => 'seg', 'tab' => 'look'], ['key' => 'icon', 'type' => 'icon', 'tab' => 'look']];
        $slot = [['key' => 'module', 'type' => 'choice', 'options' => ['' => 'Home', 'contact' => 'Contact']]];
        $hero = ['fields' => $fields, 'make' => ['title' => '_LAYOUT_PROBE', 'pic' => true]];
        $types = ['hero' => ['title' => 'Hero', 'one' => false, 'frag' => true, 'icon' => 'image', 'desc' => $hero],
            'module' => ['title' => 'Slot', 'one' => true, 'frag' => true, 'desc' => ['fields' => $slot, 'make' => ['module' => '']]]];
        $places = ['parts' => ['contacts' => ['title' => 'Contacts', 'where' => 'Foot', 'fields' => [['key' => 'phone', 'type' => 'text'], ['key' => 'web', 'type' => 'url']]]],
            'menus' => ['main' => ['title' => 'Main', 'depth' => 2]], 'zones' => []];
        $blks = ['13' => ['status' => false], 'n1' => ['new' => true, 'title' => 'New', 'kind' => 'html', 'content' => ''], '14' => ['del' => true]];
        $dft = ['layout' => $live, 'conf' => [], 'blocks' => $blks, 'bwas' => ['13' => ['title' => 'Poll', 'kind' => 'file'], '14' => ['title' => 'Old', 'kind' => 'file']]];
        $dft['layout']['pages'][9]['sections'][] = ['id' => 20, 'type' => 'hero', 'on' => true, 'title' => '', 'href' => '/nowhere', 'pic' => true, 'photo' => 'x/big.png',
            'alt' => '', 'text' => '[a](#top) [b](/about) ![](/uploads/x/big.png) [c](#gone)', 'anchor' => 'top'];
        $dft['layout']['pages'][9]['sections'][] = ['id' => 22, 'type' => 'hero', 'on' => false, 'title' => 'Off', 'href' => '//cdn.example/x', 'pic' => false,
            'photo' => 'x/big.png', 'alt' => ''];
        $dft['layout']['pages'][12] = ['title' => 'Twin', 'addr' => 'about', 'desc' => 'Said', 'sections' => []];
        $dft['layout']['theme']['phone'] = '+1';
        $dft['layout']['theme']['web'] = 'https://';
        $dft['layout']['menus']['main'][0]['title'] = 'A2';
        $dft['layout']['menus']['main'][] = ['id' => 8, 'title' => 'E', 'href' => '', 'kids' => []];
        unset($dft['layout']['menus']['main'][1]);
        $answer = static fn(string $path): bool => in_array(trim($path, '/'), ['', 'about'], true);
        $weight = static fn(string $path): int => str_contains($path, 'big') ? 600000 : 0;
        $out = ['changes' => [], 'issues' => []];
        foreach (getLayoutChanges($dft, $live, $types, $places) as $one) $out['changes'][] = $one['text'].' > '.$one['sel'];
        foreach (getLayoutIssues($dft, $types, $places, $answer, $weight) as $one) $out['issues'][] = $one['text'].' > '.$one['sel'];
        $out['look'] = getLayoutLookKeys($types['hero']['desc']);
        $lay = $live;
        $lay['pages'][9]['sections'] = [['id' => 30, 'type' => 'hero', 'on' => true, 'tone' => 'accent', 'cols' => 'two', 'pic' => false, 'title' => 'One', 'photo' => 'p/1.png'],
            ['id' => 31, 'type' => 'hero', 'on' => true, 'tone' => '', 'cols' => 'four', 'pic' => true, 'title' => 'Two', 'photo' => 'p/2.png']];
        $out['paste'] = setLayoutSecAct($lay, $types, 'look-paste', 31, '30', 9, 'sec:31');
        $out['pasted'] = array_intersect_key($lay['pages'][9]['sections'][1], ['tone' => 1, 'cols' => 1, 'pic' => 1, 'title' => 1, 'photo' => 1]);
        $out['self'] = setLayoutSecAct($lay, $types, 'look-paste', 31, '31', 9, 'sec:31');
        $out['copy'] = setLayoutSecAct($lay, $types, 'sec-copy', 30, '1', 9, 'sec:30');
        $out['copied'] = array_map(static fn(array $v): string => $v['id'].':'.$v['type'], $lay['pages'][1]['sections']);
        $out['copyslot'] = setLayoutSecAct($lay, $types, 'sec-copy', 3, '9', 1, '');
        $out['next'] = $lay['next'];
        $back = $live;
        $back['pages'][1]['sections'] = [];
        unset($back['pages'][9]);
        $old = ['layout' => array_replace_recursive($live, ['pages' => [9 => ['sections' => [['id' => 5, 'type' => 'hero', 'on' => true, 'title' => 'Was']]]]])];
        $out['back'] = setLayoutSecBack($back, $types, $old, 1, 3);
        $out['again'] = setLayoutSecBack($back, $types, $old, 1, 3);
        $out['backids'] = array_column($back['pages'][1]['sections'], 'id');
        $out['nosec'] = setLayoutSecBack($back, $types, $old, 1, 99);
        $out['nopage'] = setLayoutSecBack($back, $types, $old, 9, 5);
        $made = $live;
        $set = ['band' => false, 'sections' => [['type' => 'hero', 'make' => ['title' => 'Mine', 'anchor' => 'top']], ['type' => 'gone'],
            ['type' => 'module', 'make' => ['module' => 'contact']], ['type' => 'module'], ['type' => 'module', 'make' => ['module' => 'nope']]]];
        $pkey = setLayoutPresetPage($made, $types, $set, 'About');
        $page = $made['pages'][$pkey];
        $secs = array_map(static fn(array $v): string => $v['id'].':'.$v['type'], $page['sections']);
        $out['preset'] = ['key' => $pkey, 'addr' => $page['addr'], 'band' => $page['band'], 'next' => $made['next'], 'secs' => $secs, 'title' => $page['sections'][0]['title'],
            'anchor' => $page['sections'][0]['anchor'], 'who' => $page['sections'][0]['who']];
        $out['empty'] = $made['pages'][setLayoutPresetPage($made, $types, ['sections' => []], 'New page')]['band'];
        $out['same'] = [isLayoutSame(['b', 'a'], ['a', 'b']), isLayoutSame(true, '1'), isLayoutSame(3, '3'), isLayoutSame(null, '')];
        $out['same'] = array_merge($out['same'], [isLayoutSame('a', 'b'), isLayoutSame(false, '1'), isLayoutSame([['k' => 1], ['k' => 2]], [['k' => 2], ['k' => 1]])]);
        $pdone['data'] = $out;
    } elseif ($pmode === 'pages') {
        $conf += ['modules' => ['contact' => ['type' => 1, 'active' => 1], 'off' => ['type' => 1, 'active' => 0], 'node' => ['type' => 1, 'active' => 1]],
            'node' => ['types' => ['news' => []]]];
        $lay = Layout::getLayoutData($site);
        $lay['pages'][9]['sections'] = [['id' => 11, 'type' => 'hero', 'on' => true, 'title' => 'Hi'], ['id' => 12, 'type' => 'module', 'on' => true, 'module' => 'contact']];
        $lay['pages'][9]['desc'] = 'Said';
        $out = ['copy' => setLayoutPageCopy($lay, 9)];
        $page = $lay['pages'][20];
        $secs = array_map(static fn(array $v): string => $v['id'].':'.$v['type'], $page['sections']);
        $out['copied'] = ['title' => $page['title'], 'addr' => $page['addr'], 'desc' => $page['desc'], 'secs' => $secs];
        $out['lost'] = setLayoutPageCopy($lay, 2);
        $types = ['hero' => ['title' => 'Hero', 'one' => false, 'frag' => true, 'desc' => ['make' => ['title' => '_LAYOUT_PROBE', 'btn' => 'More']]],
            'text' => ['title' => 'Text', 'one' => false, 'frag' => true, 'desc' => ['make' => ['title' => '_LAYOUT_PROBE', 'icon' => 'file-text']]]];
        $src = "Intro line\r\n# _HOME\r\n\r\nThe lead.\r\nNot the lead.\r\n## One\r\nText **one**.\r\n\r\n## Two\r\n- a\r\n# Not a title";
        $made = setLayoutTextPage($lay, $types, $src);
        $page = $lay['pages'][$made[0]];
        $out['text'] = $made;
        $out['textpage'] = ['addr' => $page['addr'], 'desc' => $page['desc'], 'band' => $page['band']];
        foreach ($page['sections'] as $sec) $out['textsecs'][] = [$sec['type'], $sec['title'], $sec['text'] ?? $sec['body'] ?? '', $sec['btn'] ?? $sec['icon'] ?? ''];
        $out['notitle'] = setLayoutTextPage($lay, $types, "## Only a part\nText");
        $out['bare'] = setLayoutTextPage($lay, [], '# Bare')[1] !== '' ? $lay['pages'][$lay['next'] - 1]['band'] : null;
        $lay['pages'][12] = ['title' => 'Slot', 'addr' => 'slot', 'sections' => [['id' => 30, 'type' => 'module', 'on' => true, 'module' => 'contact']]];
        $out['addrs'] = getLayoutAddrs($lay);
        $pdone['data'] = $out;
    } else {
        $dft = ['layout' => Layout::getLayoutData($site), 'blocks' => [], 'bwas' => []];
        $row = ['id' => '13', 'title' => 'Poll', 'status' => true, 'bpos' => 'r', 'weight' => 6, 'which' => ['all'], 'new' => false];
        setLayoutBlock($dft, $row, ['status' => false]);
        $out = ['off' => $dft['blocks'], 'was' => $dft['bwas']];
        setLayoutBlock($dft, array_replace($row, ['status' => false]), ['title' => 'Vote']);
        $out['two'] = $dft['blocks'];
        setLayoutBlock($dft, array_replace($row, ['status' => false, 'title' => 'Vote']), ['status' => true, 'title' => 'Poll']);
        $out['back'] = $dft['blocks'];
        $out['kept'] = array_keys($dft['bwas']);
        setLayoutBlock($dft, array_replace($row, ['id' => '14', 'which' => ['ihome', 'forum']]), ['which' => ['forum', 'ihome']]);
        $out['places'] = $dft['blocks'];
        setLayoutBlock($dft, ['id' => 'n1', 'title' => 'New', 'status' => false, 'new' => true], ['status' => true]);
        $out['new'] = $dft['blocks']['n1'];
        $pdone['data'] = $out;
    }
} catch (Throwable $err) {
    $pdone['error'] = $err->getMessage().' @'.basename($err->getFile()).':'.$err->getLine();
}
echo json_encode($pdone);
