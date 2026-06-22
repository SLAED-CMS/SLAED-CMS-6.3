<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $tpl;

$rows = '';
$result = getForumTopics('id, title, time, lname, lpost, ltime, status', '97, 98', 3);
while ([$id, $title, $time, $lname, $lpost, $ltime, $status] = $db->getSqlRow($result)) {
    $title = getDecodedText($title);
    $rows .= $tpl->getHtmlFrag('table-row', [
        'is_hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
        'cells' => [
            ['href' => 'index.php?name=forum&amp;op=view&amp;id='.$id.'&amp;last#'.$lpost, 'title' => $title, 'text' => $title, 'is_truncate' => true],
            ['primary_text' => _POSTEDBY.': '.$lname, 'secondary_text' => _DATE.': '.format_time($ltime, _TIMESTRING)],
        ],
    ]);
}
$content = $tpl->getHtmlFrag('table', ['is_list' => true, 'rows_html' => $rows, 'headers' => [['text' => _FORUM], ['text' => _POSTEDBY]]]);
