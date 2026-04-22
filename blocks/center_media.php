<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $conf, $tpl;
$strip = 25;

# Last added files
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_files WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($id, $title) = $db->getSqlRow($result)) {
	$col1 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=files&amp;op=view&amp;id='.$id,
		'title' => $title,
		'label' => cutstr($title, $strip),
		'suffix_html' => '<br>',
	]);
}

# Last added pages
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_pages WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($pid, $title) = $db->getSqlRow($result)) {
	$col2 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=pages&amp;op=view&amp;id='.$pid,
		'title' => $title,
		'label' => cutstr($title, $strip),
		'suffix_html' => '<br>',
	]);
}

# Last added media
$col3 = '';
$result = $db->getSqlQuery('SELECT id, title, subtitle FROM '.PREFIX_DB."_media WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($id, $title, $subtitle) = $db->getSqlRow($result)) {
	$mtitle = $title.' '.urldecode($conf['defis']).' '.$subtitle;
	$col3 .= $tpl->getHtmlFrag('link', [
		'href' => 'index.php?name=media&amp;op=view&amp;id='.$id,
		'title' => $mtitle,
		'label' => cutstr($mtitle, $strip),
		'suffix_html' => '<br>',
	]);
}

$content = $tpl->getHtmlFrag('table', ['open' => true, 'headers' => [['text' => _FILES], ['text' => _PAGES], ['text' => _MEDIA]]]);
$content .= $tpl->getHtmlFrag('table-row', ['cells' => [['content_html' => $col1], ['content_html' => $col2], ['content_html' => $col3]]]);
$content .= $tpl->getHtmlFrag('table', []);
