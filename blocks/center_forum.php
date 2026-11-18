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

$rows = [];
$result = getForumTopics('id, uid, name, title, time, comments, counter, luid, lname, lpost, ltime, status', '97, 98', 15);
while ([$id, $uid, $uname, $title, $time, $comments, $counter, $luid, $lname, $lpost, $ltime, $status, $path, $upath, $lpath] = $db->getSqlRow($result)) {
    $title = getDecodedText($title);
    $tflag = getForumTopicState((int)$status, $time, $ltime, (int)$comments, (int)$pop, $ulast, $ismod);
    $rows[] = [
        'href' => getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'route' => $path, 'last' => 1]).'#'.$lpost, 'title' => $title,
        'mark_html' => $tflag ? $tpl->getHtmlFrag('inline-badge', ['title_text' => $title, 'label' => '', $tflag => true]) : '',
        'is_hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
        'author_html' => $uid ? user_info($uname, $upath) : $uname, 'replies' => $comments, 'views' => $counter,
        'last_html' => $luid ? user_info($lname, $lpath, false) : $lname,
    ];
}
$content = $tpl->getHtmlPart('forum-recent', ['rows' => $rows, 'author_label' => _POSTER, 'replies_label' => _REPLIES, 'views_label' => _VIEWS,
    'last_label' => _LASTPOSTER]);
