<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $conf;

# Количество сообщений в блоке
$blimit = '15';
# Закрытые форумы, сообщения которых не будут показаны в блоке
$bclos = '97, 98';

$bwhere = ($bclos) ? 'catid NOT IN ('.$bclos.') AND' : '';
$ordern = (is_moder('forum')) ? '' : "AND time <= now() AND status > '1'";
$buffer = '';
$result = $db->getSqlQuery('SELECT id, uid, name, title, time, hometext, comments, counter, luid, lname, lpost, ltime, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
while (list($id, $uid, $uname, $title, $time, $hometext, $comments, $counter, $luid, $lname, $lpost, $ltime, $status) = $db->getSqlRow($result)) {
	$thref = getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'title' => $title]);
	if (!($conf['rewrite'] ?? false)) $thref .= '&amp;last';
	$post = ($uid) ? user_info($uname) : $uname;
	$lposter = ($luid) ? user_info($lname) : $lname;
	$class = ($status <= 1 || $time > date('Y-m-d H:i:s')) ? ' class="sl_hidden"' : '';
	$buffer .= '<tr class="forum-line"><td'.$class.'><a href="'.$thref.'#'.$lpost.'" title="'.$title.'">'.cutstr($title, 50).'</a></td><td>'.$post.'</td><td>'.$comments.'</td><td>'.$counter.'</td><td>'.$lposter.'</td></tr>';
}
$content .= '<table class="sl_table_list_sort"><thead><tr class="forum-table-head"><th>'._NEWTOPICS.'</th><th>'._POSTER.'</th><th class="fl-col-num">'._REPLIES.'</th><th class="fl-col-num">'._VIEWS.'</th><th>'._LASTPOSTER.'</th></tr></thead><tbody>'.$buffer.'</tbody></table>';