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

$bicon = 'chat-dots-fill';
$bhref = 'index.php?name=forum';

$uinfo = getUserInfo();
$ulast = (is_array($uinfo) && !empty($uinfo['lastvis'])) ? (int)$uinfo['lastvis'] : 0;
$ismod = is_moder('forum');
$pop = $conf['forum']['pop'] ?? 25;

$rows = '';
$result = getForumTopics('id, uid, name, title, time, comments, counter, luid, lname, lpost, ltime, status', '97, 98', 15);
while ([$id, $uid, $uname, $title, $time, $comments, $counter, $luid, $lname, $lpost, $ltime, $status] = $db->getSqlRow($result)) {
    $thref = getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'title' => $title]);
    if (!($conf['rewrite'] ?? false)) $thref .= '&amp;last';
    $title = getDecodedText($title);

    $tflag = getForumTopicState((int)$status, $time, $ltime, (int)$comments, (int)$pop, $ulast, $ismod);
    $ticon = $tflag ? $tpl->getHtmlFrag('inline-badge', ['title_text' => $title, 'label' => '', $tflag => true]) : '';
    $tlink = $tpl->getHtmlFrag('link', ['href' => $thref.'#'.$lpost, 'title' => $title, 'label_html' => $ticon, 'label' => $title]);

    $rows .= $tpl->getHtmlFrag('table-row', [
        'is_hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
        'cells' => [
            ['content_html' => $tlink, 'is_truncate' => true],
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
