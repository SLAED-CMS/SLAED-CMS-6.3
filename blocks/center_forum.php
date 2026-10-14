<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $conf, $tpl, $user;

$bicon = 'chat-dots-fill';
$bhref = getSeoUrl(['name' => 'forum']);

$ulast = getUserLastVisit(is_user() ? intval($user[0]) : 0);
$ismod = is_moder('forum');
$pop = $conf['forum']['pop'] ?? 25;

$rows = '';
$result = getForumTopics('id, uid, name, title, time, comments, counter, luid, lname, lpost, ltime, status', '97, 98', 15);
while ([$id, $uid, $uname, $title, $time, $comments, $counter, $luid, $lname, $lpost, $ltime, $status, $path, $upath, $lpath] = $db->getSqlRow($result)) {
    $thref = getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'route' => $path, 'last' => 1]);
    $title = getDecodedText($title);

    $tflag = getForumTopicState((int)$status, $time, $ltime, (int)$comments, (int)$pop, $ulast, $ismod);
    $ticon = $tflag ? $tpl->getHtmlFrag('inline-badge', ['title_text' => $title, 'label' => '', $tflag => true]) : '';
    $tlink = $tpl->getHtmlFrag('link', ['href' => $thref.'#'.$lpost, 'title' => $title, 'label_html' => $ticon, 'label' => $title]);

    $rows .= $tpl->getHtmlFrag('table-row', [
        'is_hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
        'cells' => [
            ['content_html' => $tlink, 'is_truncate' => true],
            ['content_html' => $uid ? user_info($uname, $upath) : $uname, 'is_forum_user' => true],
            ['text' => $comments, 'is_forum_stat' => true],
            ['text' => $counter, 'is_forum_stat' => true],
            ['content_html' => $luid ? user_info($lname, $lpath) : $lname, 'is_forum_user' => true],
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
