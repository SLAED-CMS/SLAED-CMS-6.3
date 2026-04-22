<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;
$strip = 40;

# Last added jokes
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_jokes WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($jokeid, $title) = $db->getSqlRow($result)) {
	$col1 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=jokes#'.$jokeid,
		'title' => $title,
		'label' => cutstr($title, $strip),
		'suffix_html' => '<br>',
	]);
}

# Last added faq
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($fid, $title) = $db->getSqlRow($result)) {
	$col2 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=faq&amp;op=view&amp;id='.$fid,
		'title' => $title,
		'label' => cutstr($title, $strip),
		'suffix_html' => '<br>',
	]);
}

$content = $tpl->getHtmlFrag('table', ['open' => true, 'headers' => [['text' => _JOKES, 'class' => 'sl-col-half'], ['text' => _FAQ]]]);
$content .= $tpl->getHtmlFrag('table-row', ['cells' => [['content_html' => $col1], ['content_html' => $col2]]]);
$content .= $tpl->getHtmlFrag('table', []);
