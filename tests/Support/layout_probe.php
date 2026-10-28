<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the draft of the site builder: the shipped functions of admin/modules/layout.php lifted out of their source and run over stubs
# Nothing boots the core or touches a database; LAYOUT_DIR is the scratch root the caller passed, so the draft is written there alone
# Modes: draft writes and reads the draft; value checks the values of fields; sec acts on sections; menu nests and orders items; block lays changes over a row
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
if ($pwork === '' || !in_array($pmode, ['draft', 'value', 'sec', 'menu', 'block'], true)) {
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

try {
    require_once $psite.'/core/classes/layout.php';
    require_once $psite.'/core/classes/field.php';
    setProbeLift($psite.'/core/system.php', ['filterSlug', 'getConst'], $pwork.'/lift_core.php');
    $plift = ['getLayoutDraft', 'setLayoutFile', 'setLayoutDraft', 'getLayoutState', 'isLayoutDirty', 'getLayoutSecPlace', 'getLayoutItemPlace', 'getLayoutValue'];
    $plift[] = 'setLayoutValues';
    $plift = array_merge($plift, ['setLayoutBlock', 'getLayoutMake', 'getLayoutAddr', 'setLayoutSecAct', 'setLayoutMenuAct', 'setLayoutItemAct']);
    setProbeLift($psite.'/admin/modules/layout.php', $plift, $pwork.'/lift_layout.php');
    $site = ['v' => 1, 'shipped' => false, 'next' => 20, 'home' => 1, 'notfound' => 2, 'theme' => ['phone' => '', 'social' => []],
        'menus' => ['main' => [['id' => 4, 'title' => 'A', 'kids' => []], ['id' => 5, 'title' => 'B', 'kids' => []],
            ['id' => 6, 'title' => 'C', 'kids' => [['id' => 7, 'title' => 'D', 'kids' => []]]]]],
        'pages' => [1 => ['title' => '', 'sections' => [['id' => 3, 'type' => 'module', 'on' => true, 'module' => '']]], 2 => ['title' => '', 'sections' => []],
            9 => ['title' => 'About', 'addr' => 'about', 'sections' => []]]];
    $conf = ['layout' => $site];
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
        $types = ['hero' => ['title' => 'Hero', 'one' => false, 'desc' => ['make' => ['title' => '_LAYOUT_PROBE', 'items' => [['text' => '_LAYOUT_PROBE']]]]],
            'module' => ['title' => 'Slot', 'one' => true, 'desc' => ['make' => ['module' => '']]]];
        $out = [];
        $out['add'] = setLayoutSecAct($lay, $types, 'sec-add', 0, 'hero', 1, 'sec:3');
        $out['slot'] = setLayoutSecAct($lay, $types, 'sec-add', 0, 'module', 1, '');
        $out['dup'] = setLayoutSecAct($lay, $types, 'sec-dup', 20, '', 1, 'sec:20');
        $out['dupslot'] = setLayoutSecAct($lay, $types, 'sec-dup', 3, '', 1, 'sec:3');
        $out['up'] = setLayoutSecAct($lay, $types, 'sec-up', 21, '', 1, 'sec:21');
        $out['order'] = array_column($lay['pages'][1]['sections'], 'id');
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
