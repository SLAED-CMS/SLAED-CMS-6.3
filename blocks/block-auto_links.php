<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db;
$content = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_auto_links WHERE hits != '0' ORDER BY hits DESC LIMIT 0,".intval($conf['auto_links']['limit']).'');
while(list($a_id, $a_site, $a_description) = $db->getSqlRow($result)) {
	$a_site = cutstr($a_site, $conf['auto_links']['strip']);
	$title = filterText(cutstr(filterReplaceText(filterMarkdown($a_description, '', false), ''), 250), 1);
	$content .= '<table class="sl_table_block"><tr><td><a href="index.php?name=auto_links&amp;op=view&amp;id='.$a_id.'" target="_blank" title="'.$title.'" >'.$a_site.'</a></td></tr></table>';
}
$content .= '<p class="sl_center"><a href="index.php?name=auto_links&amp;op=add" title="'._A_LINKS.'" class="sl_but_blue">'._ADD.'</a></p>';
