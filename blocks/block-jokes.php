<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db;
$strip = 20;
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_jokes WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while(list($jokeid, $title) = $db->getSqlRow($result)) {
	$linkstrip = cutstr($title, $strip);
	$content .= '<table class="sl_table_block"><tr><td><a href="index.php?name=jokes#'.$jokeid.'" title="'.$title.'">'.$linkstrip.'</a></td></tr></table>';
}