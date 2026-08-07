<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for batch 6 of docs/EDITOR-UPLOADS-2026.md: the room a field has and the write guard that holds a text to it
# boots the real core like index.php, so the room table, Parser::EMBEDMAX and the shipped locale files answer the way a request would rather than the way a fixture would
# The caller hands the store names it read off the call sites in a file, because scanning the tree belongs to the test and a Windows shell strips the quotes out of an argument
# Nothing under the site tree is read or written: every scenario is one call into checkEditorTextRoom(), which measures a string and never stores one
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
if (!class_exists('Parser')) require_once BASE_DIR.'/core/classes/parser.php';

# One text of exactly the requested byte length, built out of a single byte character so what the guard measures is the count that was asked for
function getProbeText(int $size): string {
    return str_repeat('a', $size);
}

# One data URI carrying exactly the requested binary weight under the requested media type, which is the pair the guard measures rather than the length of the text around it
function getProbeData(string $type, int $size): string {
    return 'data:'.$type.';base64,'.base64_encode(str_repeat("\x01", $size));
}

# The room every named store resolves to, plus the store no call site declares, which has to answer the narrowest field there is
function getProbeRooms(array $list): array {
    $out = [];
    foreach ($list as $store) $out[(string)$store] = getEditorRoomData((string)$store);
    $out[''] = getEditorRoomData('');
    return $out;
}

# Every refusal the guard can answer and every text it has to pass, each named after the property it proves
# The Cyrillic pair decides whether the guard works at all on the sites this project serves: a character count lets cyrover through and the database answers ERROR 1406
function getProbeGuard(): array {
    $text = 65535;
    $half = intdiv($text, 2);
    return [
        'fit' => checkEditorTextRoom(getProbeText($text), 'news.intro'),
        'over' => checkEditorTextRoom(getProbeText($text + 1), 'news.intro'),
        'cyrfit' => checkEditorTextRoom(str_repeat('я', $half), 'news.intro'),
        'cyrover' => checkEditorTextRoom(str_repeat('я', $half + 1), 'news.intro'),
        'wide' => checkEditorTextRoom(getProbeText($text + 1), 'news.body'),
        'cfgover' => checkEditorTextRoom(getProbeText($text + 1), 'config'),
        'edge' => checkEditorTextRoom(getProbeData('image/png', Parser::EMBEDMAX), 'news.body'),
        'big' => checkEditorTextRoom(getProbeData('image/png', Parser::EMBEDMAX + 1), 'news.body'),
        'png' => checkEditorTextRoom(getProbeData('image/png', 512), 'news.body'),
        'small' => checkEditorTextRoom(getProbeData('image/png', 512), 'news.intro'),
        'link' => checkEditorTextRoom('![photo](https://files.example.net/photo.png)', 'news.intro'),
        'prose' => checkEditorTextRoom('plain prose that carries no image at all', 'news.intro'),
        'pdf' => checkEditorTextRoom(getProbeData('application/pdf', 512), 'news.body'),
        'svg' => checkEditorTextRoom(getProbeData('image/svg+xml', 512), 'news.body'),
        'odd' => checkEditorTextRoom(getProbeData('image/tiff', 512), 'news.body'),
        'tail' => checkEditorTextRoom('text '.getProbeData('image/png', 512).' more '.getProbeData('application/pdf', 512), 'news.body'),
        'none' => checkEditorTextRoom(getProbeData('image/png', 512), 'nosuchtable.nosuchcolumn'),
    ];
}

$path = (string)($argv[1] ?? '');
$list = json_decode(is_file($path) ? (string)file_get_contents($path) : '[]', true);
$out = [];
try {
    $out = [
        'rooms' => getProbeRooms(is_array($list) ? $list : []),
        'guard' => getProbeGuard(),
        'embedmax' => Parser::EMBEDMAX,
        'embedimg' => Parser::EMBEDIMG,
        'says' => [
            'long' => sprintf(_ETEXTLONG, filterSize(65535)),
            'noembed' => _ENOEMBED,
            'size' => _ERROR_SIZE,
            'file' => _ERROR_FILE,
        ],
    ];
} catch (Throwable $error) {
    $out = ['error' => $error->getMessage().' @ '.$error->getFile().':'.$error->getLine()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
