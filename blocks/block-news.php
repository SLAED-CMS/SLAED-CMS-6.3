<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db;
$strip = 20;
$result = $db->getSqlQuery('SELECT sid, title FROM '.PREFIX_DB."_news WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while(list($sid, $title) = $db->getSqlRow($result)) {
	$linkstrip = cutstr($title, $strip);
	$content .= '<table class="sl_table_block"><tr><td><a href="index.php?name=news&amp;op=view&amp;id='.$sid.'" title="'.$title.'">'.$linkstrip.'</a></td></tr></table>';
}