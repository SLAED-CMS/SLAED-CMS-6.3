<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the parts every panel screen takes: the meter of a real limit, a cell cut short and a list inside its window
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/lang/en.php';
require_once BASE_DIR.'/admin/lang/en.php';
$tpl = new Template('admin');
$pfull = str_repeat('x', CELL_LONG - 1);
$plist = ['head' => [['content' => 'Name']], 'rows' => [['cells' => [['content_html' => 'row']]]], 'count' => 1,
    'head_html' => '<p id="head"></p>', 'note_html' => '<p id="note"></p>'];
echo json_encode([
    'cell_none' => getTplLongCell('', 'L'),
    'cell_short' => getTplLongCell($pfull, 'L'),
    'cell_wide' => array_keys(getTplLongCell(str_repeat('я', CELL_LONG - 1), 'L')),
    'cell_long' => getTplLongCell('<b>'.$pfull, 'L<'),
    'meter_low' => getTplMeter('L<', 1, 4, 'N<'),
    'meter_over' => getTplMeter('L', 8, 4),
    'meter_none' => getTplMeter('L', 0, 4),
    'meter_tiny' => getTplMeter('L', 1, 100000),
    'meter_tone' => getTplMeter('L', 8, 4, '', 'ok'),
    'meter_row' => getTplMeter('L', 1, 4, '', 'ok', '1 / 4', true),
    'list_pane' => getTplAdminList($plist + ['pane' => ['title' => 'T<', 'icon' => 'database', 'meta' => 'M', 'tiles_html' => '<p id="tiles"></p>']]),
    'list_bare' => getTplAdminList($plist),
], JSON_UNESCAPED_UNICODE);
