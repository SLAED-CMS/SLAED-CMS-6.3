<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl, $prs;
$strip = 40;

# Last added links
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_links WHERE status != '0' ORDER BY time DESC LIMIT 0,10");
while (list($l_lid, $l_title, $l_description) = $db->getSqlRow($result)) {
	$col1 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=links&amp;op=view&amp;id='.$l_lid,
		'title' => filterText(cutstr($prs->filterContent($l_description, false, 'links'), 250), 1),
		'label' => cutstr($l_title, $strip),
		'is_line_break' => true,
	]);
}

# Last best links
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_links WHERE status != '0' ORDER BY tvotes DESC LIMIT 0,10");
while (list($l_lid, $l_title, $l_description) = $db->getSqlRow($result)) {
	$col2 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=links&amp;op=view&amp;id='.$l_lid,
		'title' => filterText(cutstr($prs->filterContent($l_description, false, 'links'), 250), 1),
		'label' => cutstr($l_title, $strip),
		'is_line_break' => true,
	]);
}

$content = $tpl->getHtmlFrag('table', ['open' => true, 'headers' => [['text' => 'Новые сайты', 'is_col_half' => true], ['text' => 'Лучшие сайты']]]);
$content .= $tpl->getHtmlFrag('table-row', ['cells' => [['content_html' => $col1], ['content_html' => $col2]]]);
$content .= $tpl->getHtmlFrag('table', []);
