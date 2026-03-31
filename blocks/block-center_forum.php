<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $conf, $tpl;

# Количество сообщений в блоке
$blimit = '15';
# Закрытые форумы, сообщения которых не будут показаны в блоке
$bclos = '97, 98';

$bwhere = ($bclos) ? 'cid NOT IN ('.$bclos.') AND' : '';
$ordern = (is_moder('forum')) ? '' : "AND time <= now() AND status > '1'";
$rows_html = '';
$result = $db->getSqlQuery('SELECT id, uid, name, title, time, body, comments, counter, luid, lname, lpost, ltime, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
while (list($id, $uid, $uname, $title, $time, $hometext, $comments, $counter, $luid, $lname, $lpost, $ltime, $status) = $db->getSqlRow($result)) {
	$thref = getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'title' => $title]);
	if (!($conf['rewrite'] ?? false)) $thref .= '&amp;last';
	$post = ($uid) ? user_info($uname) : $uname;
	$lposter = ($luid) ? user_info($lname) : $lname;
	$class_attr = ($status <= 1 || $time > date('Y-m-d H:i:s')) ? 'class="sl_hidden"' : '';
	$rows_html .= $tpl->getHtmlFrag('block-center-forum-row', [
		'class_attr'  => $class_attr,
		'url'         => $thref.'#'.$lpost,
		'title'       => $title,
		'label'       => cutstr($title, 50),
		'poster_html' => $post,
		'comments'    => $comments,
		'counter'     => $counter,
		'lposter_html'=> $lposter,
	]);
}
$content .= $tpl->getHtmlFrag('block-center-forum-table', [
	'newtopics'  => _NEWTOPICS,
	'poster'     => _POSTER,
	'replies'    => _REPLIES,
	'views'      => _VIEWS,
	'lastposter' => _LASTPOSTER,
	'rows_html'  => $rows_html,
]);
