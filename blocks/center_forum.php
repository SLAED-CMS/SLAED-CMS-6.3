<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $conf, $tpl;

$rows = '';
$result = getForumTopics('id, uid, name, title, time, comments, counter, luid, lname, lpost, ltime, status', '97, 98', 15);
while ([$id, $uid, $uname, $title, $time, $comments, $counter, $luid, $lname, $lpost, $ltime, $status] = $db->getSqlRow($result)) {
    $thref = getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'title' => $title]);
    if (!($conf['rewrite'] ?? false)) $thref .= '&amp;last';
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $rows .= $tpl->getHtmlFrag('table-row', [
        'is_hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
        'cells' => [
            ['href' => $thref.'#'.$lpost, 'title' => $title, 'text' => $title, 'is_truncate' => true],
            ['content_html' => $uid ? user_info($uname, true) : $uname, 'is_forum_user' => true],
            ['text' => $comments, 'is_forum_stat' => true],
            ['text' => $counter, 'is_forum_stat' => true],
            ['content_html' => $luid ? user_info($lname, true) : $lname, 'is_forum_user' => true],
        ],
    ]);
}
$content = $tpl->getHtmlFrag('table', ['is_list' => true, 'sortable' => true, 'rows_html' => $rows, 'headers' => [
    ['text' => _NEWTOPICS],
    ['text' => _POSTER],
    ['text' => _REPLIES, 'is_forum_stat' => true],
    ['text' => _VIEWS, 'is_forum_stat' => true],
    ['text' => _LASTPOSTER],
]]);
