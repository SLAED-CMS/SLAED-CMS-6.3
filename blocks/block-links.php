<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;
$strip = 40;
$head_html = '<th style="width: 50%;">Новые сайты</th><th>Лучшие сайты</th>';

// Last added links
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_links WHERE status != '0' ORDER BY time DESC LIMIT 0,10");
while (list($l_lid, $l_title, $l_description) = $db->getSqlRow($result)) {
	$col1 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=links&amp;op=view&amp;id='.$l_lid,
		'title' => filterText(cutstr($prs->filterContent($l_description, false, 'links'), 250), 1),
		'label' => cutstr($l_title, $strip),
	]);
}

// Last best links
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_links WHERE status != '0' ORDER BY tvotes DESC LIMIT 0,10");
while (list($l_lid, $l_title, $l_description) = $db->getSqlRow($result)) {
	$col2 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=links&amp;op=view&amp;id='.$l_lid,
		'title' => filterText(cutstr($prs->filterContent($l_description, false, 'links'), 250), 1),
		'label' => cutstr($l_title, $strip),
	]);
}

$content = $tpl->getHtmlFrag('block-multi-col-table', [
	'head_html' => $head_html,
	'body_html' => '<td>'.$col1.'</td><td>'.$col2.'</td>',
]);
