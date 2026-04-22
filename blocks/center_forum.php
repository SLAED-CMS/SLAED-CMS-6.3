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
$rows = '';
$result = $db->getSqlQuery('SELECT id, uid, name, title, time, body, comments, counter, luid, lname, lpost, ltime, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
while (list($id, $uid, $uname, $title, $time, $hometext, $comments, $counter, $luid, $lname, $lpost, $ltime, $status) = $db->getSqlRow($result)) {
	$thref = getSeoUrl(['name' => 'forum', 'op' => 'view', 'id' => $id, 'title' => $title]);
	if (!($conf['rewrite'] ?? false)) $thref .= '&amp;last';
	$post = ($uid) ? user_info($uname) : $uname;
	$lposter = ($luid) ? user_info($lname) : $lname;
	$clas = ($status <= 1 || $time > date('Y-m-d H:i:s')) ? 'sl_hidden' : '';
	$rows .= $tpl->getHtmlFrag('table-row', [
		'row_class' => $clas,
		'cells' => [
			['href' => $thref.'#'.$lpost, 'title' => $title, 'text' => cutstr($title, 50)],
			['content_html' => $post],
			['text' => $comments, 'class' => 'fl-col-stat'],
			['text' => $counter, 'class' => 'fl-col-stat'],
			['content_html' => $lposter],
		],
	]);
}
$content .= $tpl->getHtmlFrag('table', ['open' => true, 'is_voting_list' => true, 'headers' => [
	['text' => _NEWTOPICS],
	['text' => _POSTER],
	['text' => _REPLIES, 'is_forum_stat' => true],
	['text' => _VIEWS, 'is_forum_stat' => true],
	['text' => _LASTPOSTER],
]]);
$content .= $rows.$tpl->getHtmlFrag('table', []);
