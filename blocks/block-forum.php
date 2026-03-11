<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db;

# Количество сообщений в блоке
$blimit = '3';
# Закрытые форумы, сообщения которых не будут показаны в блоке
$bclos = '97, 98';

$bwhere = ($bclos) ? 'cid NOT IN ('.$bclos.') AND' : '';
$ordern = (is_moder('forum')) ? '' : "AND time <= now() AND status > '1'";
$buffer = '';
$result = $db->getSqlQuery('SELECT id, title, time, luid, lname, lpost, ltime, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
while (list($id, $title, $time, $luid, $lname, $lpost, $ltime, $status) = $db->getSqlRow($result)) {
	$lposter = ($luid) ? user_info($lname) : $lname;
	$class = ($status <= 1 || $time > date('Y-m-d H:i:s')) ? ' class="sl_hidden"' : '';
	$buffer .= '<li'.$class.'><a href="index.php?name=forum&amp;op=view&amp;id='.$id.'&amp;last#'.$lpost.'" title="'.$title.'">'.cutstr($title, 50).'</a><ul><li title="'._POSTEDBY.'" class="sl_post">'.$lposter.'</li><li title="'._DATE.': '.format_time($ltime, _TIMESTRING).'" class="ico i_date">'.format_time($ltime).'</li></ul></li>';
}
$content = '<div class="grid"><p title="'._FORUM.'" class="font f_title">'._FORUM.'</p><ul class="list-item">'.$buffer.'</ul></div>';
