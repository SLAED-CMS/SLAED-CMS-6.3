<?php
# 2005 - 2026 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;
$strip = 20;
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_pages WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($pid, $title) = $db->getSqlRow($result)) {
	$content .= $tpl->getHtmlFrag('block-list-item', [
		'url'         => 'index.php?name=pages&amp;op=view&amp;id='.$pid,
		'title'       => $title,
		'label'       => cutstr($title, $strip),
		'target_attr' => '',
	]);
}
