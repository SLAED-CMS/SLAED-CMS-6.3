<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $tpl, $prs;
$content = '';
$result = $db->getSqlQuery('SELECT id, title, intro FROM '.PREFIX_DB."_auto_links WHERE hits != '0' ORDER BY hits DESC LIMIT 0,".intval($conf['auto_links']['limit']).'');
while (list($a_id, $a_site, $a_description) = $db->getSqlRow($result)) {
	$a_site = cutstr($a_site, $conf['auto_links']['strip']);
	$title = filterText(cutstr($prs->filterContent($a_description, false, ''), 250), 1);
	$content .= $tpl->getHtmlFrag('block-list-item', [
		'url'         => 'index.php?name=auto_links&amp;op=view&amp;id='.$a_id,
		'title'       => $title,
		'label'       => $a_site,
		'target_attr' => ' target="_blank"',
	]);
}
$content .= $tpl->getHtmlFrag('block-center-link', [
	'url'   => 'index.php?name=auto_links&amp;op=add',
	'title' => _A_LINKS,
	'label' => _ADD,
]);
