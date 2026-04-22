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
$rows = '';
$result = $db->getSqlQuery('SELECT id, title, time, luid, lname, lpost, ltime, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
while (list($id, $title, $time, $luid, $lname, $lpost, $ltime, $status) = $db->getSqlRow($result)) {
	$lposter = ($luid) ? user_info($lname) : $lname;
	$rows .= $tpl->getHtmlFrag('table-row', [
		'is_hidden' => $status <= 1 || $time > date('Y-m-d H:i:s'),
		'cells' => [
			['href' => 'index.php?name=forum&amp;op=view&amp;id='.$id.'&amp;last#'.$lpost, 'title' => $title, 'text' => cutstr($title, 50)],
			['primary_text' => _POSTEDBY.': '.$lposter, 'secondary_text' => _DATE.': '.format_time($ltime, _TIMESTRING)],
		],
	]);
}
$content = $tpl->getHtmlFrag('table', ['open' => true, 'is_voting_list' => true, 'headers' => [['text' => _FORUM], ['text' => _POSTEDBY]]]);
$content .= $rows.$tpl->getHtmlFrag('table', []);
