<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;
$strip = 25;
$head_html = '<th>'._FILES.'</th><th>'._PAGES.'</th><th>'._FAQ.'</th>';

// Last added files
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_files WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($id, $title) = $db->getSqlRow($result)) {
	$col1 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=files&amp;op=view&amp;id='.$id,
		'title' => $title,
		'label' => cutstr($title, $strip),
	]);
}

// Last added pages
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_pages WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($pid, $title) = $db->getSqlRow($result)) {
	$col2 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=pages&amp;op=view&amp;id='.$pid,
		'title' => $title,
		'label' => cutstr($title, $strip),
	]);
}

// Last added faq
$col3 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($fid, $title) = $db->getSqlRow($result)) {
	$col3 .= $tpl->getHtmlFrag('block-inner-list-item', [
		'url'   => 'index.php?name=faq&amp;op=view&amp;id='.$fid,
		'title' => $title,
		'label' => cutstr($title, $strip),
	]);
}

$content = $tpl->getHtmlFrag('block-multi-col-table', [
	'head_html' => $head_html,
	'body_html' => '<td>'.$col1.'</td><td>'.$col2.'</td><td>'.$col3.'</td>',
]);
