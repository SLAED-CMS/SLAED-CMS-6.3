<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl;
$strip = 20;
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_files WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($id, $title) = $db->getSqlRow($result)) {
	$content .= $tpl->getHtmlFrag('block-list-item', [
		'url'         => 'index.php?name=files&amp;op=view&amp;id='.$id,
		'title'       => $title,
		'label'       => cutstr($title, $strip),
		'target_attr' => '',
	]);
}
