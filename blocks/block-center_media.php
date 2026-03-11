<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $conf;
$strip = 25;
$content = '<table class="sl_table_list"><thead class="sl_table_list_head"><tr><th>'._FILES.'</th><th>'._PAGES.'</th><th>'._MEDIA.'</th></tr></thead><tbody class="sl_table_list_body">';

// Last added files
$content .= '<tr><td>';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_files WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while(list($id, $title) = $db->getSqlRow($result)) {
	$linkstrip = cutstr($title, $strip);
	$content .= '<table><tr><td><a href="index.php?name=files&amp;op=view&amp;id='.$id.'" title="'.$title.'">'.$linkstrip.'</a></td></tr></table>';
}

// Last added pages
$content .= '</td><td>';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_pages WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while(list($pid, $title) = $db->getSqlRow($result)) {
	$linkstrip = cutstr($title, $strip);
	$content .= '<table><tr><td><a href="index.php?name=pages&amp;op=view&amp;id='.$pid.'" title="'.$title.'">'.$linkstrip.'</a></td></tr></table>';
}

// Last added media
$content .='</td><td>';
$result = $db->getSqlQuery('SELECT id, title, subtitle FROM '.PREFIX_DB."_media WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while(list($id, $title, $subtitle) = $db->getSqlRow($result)) {
	$mtitle = $title.' '.urldecode($conf['defis']).' '.$subtitle;
	$linkstrip = cutstr($mtitle, $strip);
	$content .= '<table><tr><td><a href="index.php?name=media&amp;op=view&amp;id='.$id.'" title="'.$mtitle.'">'.$linkstrip.'</a></td></tr></table>';
}
$content .= '</td></tr></tbody></table>';