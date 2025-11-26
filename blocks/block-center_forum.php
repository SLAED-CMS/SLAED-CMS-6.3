<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $prefix, $db;

# Количество сообщений в блоке
$blimit = '15';
# Закрытые форумы, сообщения которых не будут показаны в блоке
$bclos = '97, 98';

$bwhere = ($bclos) ? "catid NOT IN (".$bclos.") AND" : "";
$ordern = (is_moder("forum")) ? "" : "AND time <= now() AND status > '1'";
$buffer = '';
$result = $db->sql_query("SELECT id, uid, name, title, time, hometext, comments, counter, l_uid, l_name, l_id, l_time, status FROM ".$prefix."_forum WHERE ".$bwhere." pid = '0' ".$ordern." ORDER BY l_time DESC LIMIT 0, ".$blimit);
while (list($id, $uid, $uname, $title, $time, $hometext, $comments, $counter, $l_uid, $l_name, $l_id, $l_time, $status) = $db->sql_fetchrow($result)) {
	$thref = getHref(array('name=forum&op=view&id='.$id.'&last', $time, $l_time, $title, $hometext, '', '', ''));
	$post = ($uid) ? user_info($uname) : $uname;
	$lposter = ($l_uid) ? user_info($l_name) : $l_name;
	$class = ($status <= 1 || $time > date('Y-m-d H:i:s')) ? ' class="sl_hidden"' : '';
	$buffer .= '<tr class="forum-line"><td'.$class.'><a href="'.$thref.'#'.$l_id.'" title="'.$title.'">'.cutstr($title, 50).'</a></td><td>'.$post.'</td><td>'.$comments.'</td><td>'.$counter.'</td><td>'.$lposter.'</td></tr>';
}
$content .= '<table class="sl_table_list_sort"><thead><tr class="forum-table-head"><th>'._NEWTOPICS.'</th><th>'._POSTER.'</th><th class="fl-col-num">'._REPLIES.'</th><th class="fl-col-num">'._VIEWS.'</th><th>'._LASTPOSTER.'</th></tr></thead><tbody>'.$buffer.'</tbody></table>';
?>