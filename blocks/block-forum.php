<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;

# Количество сообщений в блоке
$blimit = '3';
# Закрытые форумы, сообщения которых не будут показаны в блоке
$bclos = '97, 98';

$bwhere = ($bclos) ? 'cid NOT IN ('.$bclos.') AND' : '';
$ordern = (is_moder('forum')) ? '' : "AND time <= now() AND status > '1'";
$items_html = '';
$result = $db->getSqlQuery('SELECT id, title, time, luid, lname, lpost, ltime, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
while (list($id, $title, $time, $luid, $lname, $lpost, $ltime, $status) = $db->getSqlRow($result)) {
	$lposter = ($luid) ? user_info($lname) : $lname;
	$class_attr = ($status <= 1 || $time > date('Y-m-d H:i:s')) ? 'class="sl_hidden"' : '';
	$items_html .= $tpl->getHtmlFrag('block-forum-item', [
		'class_attr'      => $class_attr,
		'url'             => 'index.php?name=forum&amp;op=view&amp;id='.$id.'&amp;last#'.$lpost,
		'title'           => $title,
		'label'           => cutstr($title, 50),
		'posted_by_label' => _POSTEDBY,
		'poster_html'     => $lposter,
		'date_label'      => _DATE.': '.format_time($ltime, _TIMESTRING),
		'date'            => format_time($ltime),
	]);
}
$content = $tpl->getHtmlFrag('block-forum-list', [
	'forum_label' => _FORUM,
	'items_html'  => $items_html,
]);
