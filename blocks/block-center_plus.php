<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;
$strip = 40;
$head_html = '<th style="width: 50%;">'._JOKES.'</th><th>'._FAQ.'</th>';

// Last added jokes
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_jokes WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($jokeid, $title) = $db->getSqlRow($result)) {
	$col1 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=jokes#'.$jokeid,
		'title' => $title,
		'label' => cutstr($title, $strip),
	]);
}

// Last added faq
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($fid, $title) = $db->getSqlRow($result)) {
	$col2 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=faq&amp;op=view&amp;id='.$fid,
		'title' => $title,
		'label' => cutstr($title, $strip),
	]);
}

$content = $tpl->getHtmlFrag('block-multi-col-table', [
	'head_html' => $head_html,
	'body_html' => '<td>'.$col1.'</td><td>'.$col2.'</td>',
]);
